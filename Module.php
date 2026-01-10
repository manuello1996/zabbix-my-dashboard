<?php declare(strict_types=1);

namespace Modules\MyDashboard;

use Zabbix\Core\CModule;
use APP;
use CMenuItem;
use CMenu;
use CUrl;
use Modules\MyDashboard\MyDashboardExternalLinks;

class Module extends CModule {
    public function init(): void {
        require_once __DIR__.'/lib/ExternalLinks.php';

        $external_links = MyDashboardExternalLinks::getLinks();
        $menu_label = MyDashboardExternalLinks::getMenuEntryLabel();
        $custom_menu_items = [];

        foreach ($external_links as $link) {
            if (($link['type'] ?? 'external') === 'internal') {
                $menu_item = (new CMenuItem($link['label']))
                    ->setAction($link['value']);
            }
            else {
                $menu_item = (new CMenuItem($link['label']))
                    ->setUrl(new CUrl($link['value']));
            }

            if (($link['target'] ?? '_self') === '_blank') {
                $menu_item->setTarget('_blank');
            }

            $custom_menu_items[] = $menu_item;
        }

        APP::Component()->get('menu.main')
            ->insertBefore(
                _('Dashboards'),
                (new CMenuItem($menu_label))
                    ->setIcon('zi-home')
                    ->setSubMenu(
                        new CMenu([
                            ...$custom_menu_items
                            //(new CMenuItem(_('User profile')))
                            //    ->setAction('userprofile.edit')
                        ])
                    )
            );

        $administration = APP::Component()->get('menu.main')->find(_('Administration'));
        if ($administration !== null) {
            $administration->getSubMenu()->add(
                (new CMenuItem(_('External links')))
                    ->setUrl(
                        (new CUrl('zabbix.php'))
                            ->setArgument('action', 'external.links.edit')
                    )
                    ->setAliases(['external.links.edit'])
            );
        }
    }
}
