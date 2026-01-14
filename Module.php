<?php declare(strict_types=1);

namespace Modules\MyDashboard;

use Zabbix\Core\CModule;
use APP;
use CMenuItem;
use CMenu;
use CUrl;
use Modules\MyDashboard\MyDashboardExternalLinks;
use Modules\MyDashboard\MyDashboardUserLinks;

class Module extends CModule {
    public function init(): void {
        require_once __DIR__.'/lib/ExternalLinks.php';
        require_once __DIR__.'/lib/UserLinks.php';

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

        $user_links = MyDashboardUserLinks::getLinks();
        if ($user_links) {
            $user_menu_items = [];
            foreach ($user_links as $link) {
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

                $user_menu_items[] = $menu_item;
            }

            APP::Component()->get('menu.main')
                ->insertBefore(
                    _('Dashboards'),
                    (new CMenuItem(MyDashboardUserLinks::getMenuEntryLabel()))
                        ->setIcon('zi-link-external')
                        ->setSubMenu(new CMenu($user_menu_items))
                );
        }

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

        $user_menu = APP::Component()->get('menu.user');
        if ($user_menu !== null) {
            $user_settings = $user_menu->find(_('User settings'));
            if ($user_settings !== null) {
                $user_settings->getSubMenu()->insertAfter(
                    _('Profile'),
                    (new CMenuItem(_('Custom links')))->setAction('user.links.edit')
                );
            }
        }
    }
}
