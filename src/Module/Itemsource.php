<?php

namespace Friendica\Module;

use Friendica\Core\L10n;
use Friendica\Core\Renderer;
use Friendica\Model;

/**
 * @author Hypolite Petovan <mrpetovan@gmail.com>
 */
class Itemsource extends \Friendica\BaseModule
{
	public static function content()
	{
		if (!is_site_admin()) {
			return;
		}

		$a = self::getApp();

		if (!empty($a->argv[1])) {
			$guid = $a->argv[1];
		}

		$guid = defaults($_REQUEST['guid'], $guid);

		$source = '';
		$item_uri = '';
		if (!empty($guid)) {
			$item = Model\Item::selectFirst([], ['guid' => $guid]);

			$conversation = Model\Conversation::getByItemUri($item['uri']);

			$item_uri = $item['uri'];
			$source = $conversation['source'];
		}

		$tpl = Renderer::getMarkupTemplate('debug/itemsource.tpl');
		$o = Renderer::replaceMacros($tpl, [
			'$guid'          => ['guid', L10n::t('Item Guid'), defaults($_REQUEST, 'guid', ''), ''],
			'$source'        => $source,
			'$item_uri'      => $item_uri
		]);

		return $o;
	}
}
