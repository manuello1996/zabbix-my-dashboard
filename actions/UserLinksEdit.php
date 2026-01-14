<?php declare(strict_types=1);
namespace Modules\MyDashboard\Actions;

use CController;
use CControllerResponseData;
use CCsrfTokenHelper;
use Modules\MyDashboard\MyDashboardUserLinks;

require_once __DIR__.'/../lib/UserLinks.php';

class UserLinksEdit extends CController {
	public function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'links' => 'array',
			'menu_entry' => 'string'
		];

		$this->validateInput($fields);

		return true;
	}

	protected function checkPermissions(): bool {
		return true;
	}

	protected function doAction(): void {
		$links = MyDashboardUserLinks::getLinks();
		$menu_entry_value = MyDashboardUserLinks::getMenuEntryValue();

		if ($this->hasInput('links')) {
			$links = [];
			foreach ((array) $this->getInput('links', []) as $link) {
				$type = (($link['type'] ?? 'external') === 'internal') ? 'internal' : 'external';
				$value = (string)($link['value'] ?? '');

				if ($value === '') {
					$value = $type === 'internal'
						? (string)($link['action'] ?? '')
						: (string)($link['url'] ?? '');
				}

				$links[] = [
					'label' => (string)($link['label'] ?? ''),
					'type' => $type,
					'value' => $value,
					'target' => !empty($link['target_blank']) ? '_blank' : '_self'
				];
			}
		}

		$data = [
			'page_title' => _('User links'),
			'links' => $links,
			'menu_entry_value' => $menu_entry_value,
			'menu_entry_default' => _('User Link'),
			'form_action' => 'user.links.update',
			'csrf_token' => CCsrfTokenHelper::get('user.links.update')
		];

		$this->setResponse(new CControllerResponseData($data));
	}
}
