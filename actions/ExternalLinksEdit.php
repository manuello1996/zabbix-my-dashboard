<?php declare(strict_types=1);
namespace Modules\MyDashboard\Actions;

use CController;
use CControllerResponseData;
use CCsrfTokenHelper;
use Modules\MyDashboard\MyDashboardExternalLinks;

require_once __DIR__.'/../lib/ExternalLinks.php';

class ExternalLinksEdit extends CController {
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
		$links = MyDashboardExternalLinks::getLinks();
		$menu_entry_value = MyDashboardExternalLinks::getMenuEntryValue();

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
			'page_title' => _('External links'),
			'links' => $links,
			'menu_entry_value' => $menu_entry_value,
			'macro_entries' => MyDashboardExternalLinks::getMacroEntries(),
			'csrf_token' => CCsrfTokenHelper::get('external.links.update')
		];

		$this->setResponse(new CControllerResponseData($data));
	}
}
