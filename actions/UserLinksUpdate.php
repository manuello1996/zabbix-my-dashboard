<?php declare(strict_types=1);
namespace Modules\MyDashboard\Actions;

use CController;
use CControllerResponseRedirect;
use CMessageHelper;
use CUrl;
use Modules\MyDashboard\MyDashboardUserLinks;

require_once __DIR__.'/../lib/UserLinks.php';

class UserLinksUpdate extends CController {
	protected function checkInput(): bool {
		$fields = [
			'links' => 'array',
			'menu_entry' => 'string'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$response = new CControllerResponseRedirect(
				(new CUrl('zabbix.php'))->setArgument('action', 'user.links.edit')
			);
			$response->setFormData($this->getInputAll());
			CMessageHelper::setErrorTitle(_('Cannot update user links'));
			$this->setResponse($response);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return true;
	}

	protected function doAction(): void {
		$raw_links = $this->getInput('links', []);
		$menu_entry = (string) $this->getInput('menu_entry', '');
		$errors = [];
		$warnings = [];
		$links = MyDashboardUserLinks::normalizeLinks($raw_links, $errors, $warnings);

		$response = new CControllerResponseRedirect(
			(new CUrl('zabbix.php'))->setArgument('action', 'user.links.edit')
		);

		if ($errors) {
			foreach ($errors as $error) {
				CMessageHelper::addError($error);
			}
			CMessageHelper::setErrorTitle(_('Cannot update user links'));
			$response->setFormData($this->getInputAll());
			$this->setResponse($response);
			return;
		}

		$links_saved = MyDashboardUserLinks::saveLinks($links, $errors);
		$menu_saved = $links_saved
			? MyDashboardUserLinks::saveMenuEntryValue($menu_entry, $errors)
			: false;

		if ($links_saved && $menu_saved) {
			foreach ($warnings as $warning) {
				CMessageHelper::addWarning($warning);
			}
			CMessageHelper::setSuccessTitle(_('User links updated'));
		}
		else {
			foreach ($errors as $error) {
				CMessageHelper::addError($error);
			}
			CMessageHelper::setErrorTitle(_('Cannot update user links'));
			$response->setFormData($this->getInputAll());
		}

		$this->setResponse($response);
	}
}
