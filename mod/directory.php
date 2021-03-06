<?php
/**
 * @file mod/directory.php
 */

use Friendica\App;
use Friendica\Content\Nav;
use Friendica\Content\Pager;
use Friendica\Content\Widget;
use Friendica\Core\Config;
use Friendica\Core\Hook;
use Friendica\Core\L10n;
use Friendica\Core\Renderer;
use Friendica\Database\DBA;
use Friendica\Model\Contact;
use Friendica\Model\Profile;
use Friendica\Util\Proxy as ProxyUtils;
use Friendica\Util\Strings;

function directory_init(App $a)
{
	if (local_user()) {
		$a->page['aside'] .= Widget::findPeople();
		$a->page['aside'] .= Widget::follow();
	} else {
		unset($_SESSION['theme']);
		unset($_SESSION['mobile-theme']);
	}
}

function directory_post(App $a)
{
	if (!empty($_POST['search'])) {
		$a->data['search'] = $_POST['search'];
	}
}

function directory_content(App $a)
{
	if ((Config::get('system', 'block_public') && !local_user() && !remote_user())
		|| (Config::get('system', 'block_local_dir') && !local_user() && !remote_user())
	) {
		notice(L10n::t('Public access denied.') . EOL);
		return;
	}

	$o = '';
	Nav::setSelected('directory');

	if (!empty($a->data['search'])) {
		$search = Strings::escapeTags(trim($a->data['search']));
	} else {
		$search = (!empty($_GET['search']) ? Strings::escapeTags(trim(rawurldecode($_GET['search']))) : '');
	}

	$gdirpath = '';
	$dirurl = Config::get('system', 'directory');
	if (strlen($dirurl)) {
		$gdirpath = Profile::zrl($dirurl, true);
	}

	if ($search) {
		$search = DBA::escape($search);

		$sql_extra = " AND ((`profile`.`name` LIKE '%$search%') OR
				(`user`.`nickname` LIKE '%$search%') OR
				(`profile`.`pdesc` LIKE '%$search%') OR
				(`profile`.`locality` LIKE '%$search%') OR
				(`profile`.`region` LIKE '%$search%') OR
				(`profile`.`country-name` LIKE '%$search%') OR
				(`profile`.`gender` LIKE '%$search%') OR
				(`profile`.`marital` LIKE '%$search%') OR
				(`profile`.`sexual` LIKE '%$search%') OR
				(`profile`.`about` LIKE '%$search%') OR
				(`profile`.`romance` LIKE '%$search%') OR
				(`profile`.`work` LIKE '%$search%') OR
				(`profile`.`education` LIKE '%$search%') OR
				(`profile`.`pub_keywords` LIKE '%$search%') OR
				(`profile`.`prv_keywords` LIKE '%$search%'))";
	} else {
		$sql_extra = '';
	}

	$publish = (Config::get('system', 'publish_all') ? '' : " AND `publish` = 1 " );


	$total = 0;
	$cnt = DBA::fetchFirst("SELECT COUNT(*) AS `total` FROM `profile`
				LEFT JOIN `user` ON `user`.`uid` = `profile`.`uid`
				WHERE `is-default` $publish AND NOT `user`.`blocked` AND NOT `user`.`account_removed` $sql_extra");
	if (DBA::isResult($cnt)) {
		$total = $cnt['total'];
	}
	$pager = new Pager($a->query_string, 60);

	$order = " ORDER BY `name` ASC ";

	$limit = $pager->getStart()."," . $pager->getItemsPerPage();

	$r = DBA::p("SELECT `profile`.*, `profile`.`uid` AS `profile_uid`, `user`.`nickname`, `user`.`timezone` , `user`.`page-flags`,
			`contact`.`addr`, `contact`.`url` AS profile_url FROM `profile`
			LEFT JOIN `user` ON `user`.`uid` = `profile`.`uid`
			LEFT JOIN `contact` ON `contact`.`uid` = `user`.`uid`
			WHERE `is-default` $publish AND NOT `user`.`blocked` AND NOT `user`.`account_removed` AND `contact`.`self`
			$sql_extra $order LIMIT $limit"
	);
	if (DBA::isResult($r)) {
		if (in_array('small', $a->argv)) {
			$photo = 'thumb';
		} else {
			$photo = 'photo';
		}

		$entries = [];

		while ($rr = DBA::fetch($r)) {
			$itemurl = (($rr['addr'] != "") ? $rr['addr'] : $rr['profile_url']);

			$profile_link = $rr['profile_url'];

			$pdesc = (($rr['pdesc']) ? $rr['pdesc'] . '<br />' : '');

			$details = '';
			if (strlen($rr['locality'])) {
				$details .= $rr['locality'];
			}
			if (strlen($rr['region'])) {
				if (strlen($rr['locality'])) {
					$details .= ', ';
				}
				$details .= $rr['region'];
			}
			if (strlen($rr['country-name'])) {
				if (strlen($details)) {
					$details .= ', ';
				}
				$details .= $rr['country-name'];
			}
//			if(strlen($rr['dob'])) {
//				if(($years = age($rr['dob'],$rr['timezone'],'')) != 0)
//					$details .= '<br />' . L10n::t('Age: ') . $years;
//			}
//			if(strlen($rr['gender']))
//				$details .= '<br />' . L10n::t('Gender: ') . $rr['gender'];

			$profile = $rr;

			if (!empty($profile['address'])
				|| !empty($profile['locality'])
				|| !empty($profile['region'])
				|| !empty($profile['postal-code'])
				|| !empty($profile['country-name'])
			) {
				$location = L10n::t('Location:');
			} else {
				$location = '';
			}

			$gender   = (!empty($profile['gender']) ? L10n::t('Gender:')   : false);
			$marital  = (!empty($profile['marital']) ? L10n::t('Status:')   : false);
			$homepage = (!empty($profile['homepage']) ? L10n::t('Homepage:') : false);
			$about    = (!empty($profile['about']) ? L10n::t('About:')    : false);

			$location_e = $location;

			$photo_menu = [
				'profile' => [L10n::t("View Profile"), Contact::magicLink($profile_link)]
			];

			$entry = [
				'id'           => $rr['id'],
				'url'          => Contact::magicLInk($profile_link),
				'itemurl'      => $itemurl,
				'thumb'        => ProxyUtils::proxifyUrl($rr[$photo], false, ProxyUtils::SIZE_THUMB),
				'img_hover'    => $rr['name'],
				'name'         => $rr['name'],
				'details'      => $details,
				'account_type' => Contact::getAccountType($rr),
				'profile'      => $profile,
				'location'     => $location_e,
				'tags'         => $rr['pub_keywords'],
				'gender'       => $gender,
				'pdesc'        => $pdesc,
				'marital'      => $marital,
				'homepage'     => $homepage,
				'about'        => $about,
				'photo_menu'   => $photo_menu,

			];

			$arr = ['contact' => $rr, 'entry' => $entry];

			Hook::callAll('directory_item', $arr);

			unset($profile);
			unset($location);

			if (!$arr['entry']) {
				continue;
			}

			$entries[] = $arr['entry'];
		}
		DBA::close($r);

		$tpl = Renderer::getMarkupTemplate('directory_header.tpl');

		$o .= Renderer::replaceMacros($tpl, [
			'$search'    => $search,
			'$globaldir' => L10n::t('Global Directory'),
			'$gdirpath'  => $gdirpath,
			'$desc'      => L10n::t('Find on this site'),
			'$contacts'  => $entries,
			'$finding'   => L10n::t('Results for:'),
			'$findterm'  => (strlen($search) ? $search : ""),
			'$title'     => L10n::t('Site Directory'),
			'$submit'    => L10n::t('Find'),
			'$paginate'  => $pager->renderFull($total),
		]);
	} else {
		info(L10n::t("No entries \x28some entries may be hidden\x29.") . EOL);
	}

	return $o;
}
