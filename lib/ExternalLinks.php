<?php declare(strict_types=1);

namespace Modules\MyDashboard;

use API;
use CHtmlUrlValidator;
use DB;

final class MyDashboardExternalLinks {
	private const MACRO_PREFIX = '{$MYDASHBOARD_EXTERNAL_LINK_';
	private const MACRO_SUFFIX = '}';
	private const MACRO_DESCRIPTION = 'Custom My Dashboard external link (JSON).';
	private const MENU_ENTRY_MACRO = '{$MYDASHBOARD_MENU_ENTRY}';

	public static function getLinks(): array {
		self::ensureDefaultPermissionOverview();
		$macros = self::getLinkMacros(self::MACRO_PREFIX);

		if ($macros) {
			return self::decodeMacroLinks($macros);
		}

		return [];
	}

	public static function getMenuEntryLabel(): string {
		$macro = self::getMenuEntryMacro();

		if ($macro !== null) {
			$label = trim((string) $macro['value']);
			if ($label !== '') {
				return $label;
			}
		}

		return _('My Zabbix Home');
	}

	public static function getMenuEntryValue(): string {
		$macro = self::getMenuEntryMacro();
		if ($macro === null) {
			return '';
		}

		return trim((string) $macro['value']);
	}

	public static function saveMenuEntryValue(string $value, array &$errors): bool {
		$value = trim($value);
		$existing = self::getMenuEntryMacro();

		if ($value === '') {
			if ($existing !== null) {
				API::UserMacro()->deleteGlobal([$existing['globalmacroid']]);
			}
			return true;
		}

		$max_length = DB::getFieldLength('globalmacro', 'value');
		if (strlen($value) > $max_length) {
			$errors[] = _s('Menu entry exceeds the maximum size of %1$d characters.', $max_length);
			return false;
		}

		if ($existing === null) {
			API::UserMacro()->createGlobal([[
				'macro' => self::MENU_ENTRY_MACRO,
				'value' => $value,
				'description' => 'Custom My Dashboard menu entry label.'
			]]);
		}
		else {
			API::UserMacro()->updateGlobal([[
				'globalmacroid' => $existing['globalmacroid'],
				'value' => $value
			]]);
		}

		return true;
	}

	public static function normalizeLinks(array $raw_links, array &$errors, array &$warnings): array {
		$links = [];

		foreach ($raw_links as $index => $raw_link) {
			if (!is_array($raw_link)) {
				continue;
			}

			$label = trim((string)($raw_link['label'] ?? ''));
			$type = ($raw_link['type'] ?? 'external') === 'internal' ? 'internal' : 'external';
			$value = trim((string)($raw_link['value'] ?? ''));
			if ($value === '') {
				$value = ($type === 'internal')
					? trim((string)($raw_link['action'] ?? ''))
					: trim((string)($raw_link['url'] ?? ''));
			}
			$target = !empty($raw_link['target_blank']) ? '_blank' : '_self';

			if ($label === '' && $value === '') {
				continue;
			}

			if ($label === '') {
				$errors[] = _s('Row %1$d: Label is required.', $index + 1);
				continue;
			}

			if ($type === 'external') {
				if ($value === '') {
					$errors[] = _s('Row %1$d: URL is required.', $index + 1);
					continue;
				}

				if (!CHtmlUrlValidator::validate($value)) {
					$errors[] = _s('Row %1$d: URL "%2$s" is invalid.', $index + 1, $value);
					continue;
				}
			}
			else {
				if ($value === '') {
					$errors[] = _s('Row %1$d: Action is required.', $index + 1);
					continue;
				}
			}

			if (mb_strlen($label) > 64) {
				$warnings[] = _s('Row %1$d: Label truncated to 64 characters.', $index + 1);
				$label = mb_substr($label, 0, 64);
			}

			$links[] = [
				'label' => $label,
				'type' => $type,
				'value' => $value,
				'target' => $target
			];
		}

		return $links;
	}

	public static function saveLinks(array $links, array &$errors): bool {
		$compact = [];
		foreach ($links as $link) {
			$compact[] = [
				'l' => $link['label'] ?? '',
				't' => $link['type'] ?? 'external',
				'v' => $link['value'] ?? '',
				'tr' => $link['target'] ?? '_self'
			];
		}

		$max_length = DB::getFieldLength('globalmacro', 'value');

		$encoded_links = [];
		foreach ($compact as $index => $link) {
			$encoded = json_encode($link, JSON_UNESCAPED_SLASHES);
			if ($encoded === false) {
				$errors[] = _('Failed to encode external links.');
				return false;
			}
			if (strlen($encoded) > $max_length) {
				$errors[] = _s('External links exceed the maximum size of %1$d characters.', $max_length);
				return false;
			}
			$encoded_links[$index] = $encoded;
		}

		if (!$encoded_links) {
			$existing_new = self::getLinkMacros(self::MACRO_PREFIX);
			if ($existing_new) {
				$ids = array_column($existing_new, 'globalmacroid');
				API::UserMacro()->deleteGlobal($ids);
			}

			return true;
		}

		// Always recreate macros in order using the singular prefix.
		$existing = self::getLinkMacros(self::MACRO_PREFIX);
		if ($existing) {
			$ids = array_column($existing, 'globalmacroid');
			API::UserMacro()->deleteGlobal($ids);
		}

		$create = [];
		foreach ($encoded_links as $position => $value) {
			$create[] = [
				'macro' => self::buildMacroName($position + 1, self::MACRO_PREFIX),
				'value' => $value,
				'description' => self::MACRO_DESCRIPTION
			];
		}

		API::UserMacro()->createGlobal($create);

		return true;
	}

	public static function hasManageAccess(): bool {
		return true;
	}

	public static function getMacroEntries(): array {
		$entries = [];
		$macros = self::getLinkMacros(self::MACRO_PREFIX);
		foreach ($macros as $macro) {
			$entries[] = [
				'macro' => $macro['macro'],
				'value' => $macro['value']
			];
		}

		return $entries;
	}

	private static function getLinkMacros(string $prefix): array {
		$macros = API::UserMacro()->get([
			'output' => ['globalmacroid', 'macro', 'value'],
			'globalmacro' => true
		]) ?: [];

		$matched = [];
		$prefix_length = strlen($prefix);
		foreach ($macros as $macro) {
			$name = $macro['macro'] ?? '';
			if (strncmp($name, $prefix, $prefix_length) !== 0) {
				continue;
			}
			if (substr($name, -1) !== self::MACRO_SUFFIX) {
				continue;
			}
			$suffix = substr($name, $prefix_length, -1);
			if ($suffix === '' || !ctype_digit($suffix)) {
				continue;
			}
			$matched[(int) $suffix] = $macro;
		}

		if (!$matched) {
			return [];
		}

		ksort($matched, SORT_NUMERIC);

		return array_values($matched);
	}

	private static function getMenuEntryMacro(): ?array {
		$macros = API::UserMacro()->get([
			'output' => ['globalmacroid', 'macro', 'value'],
			'globalmacro' => true,
			'filter' => ['macro' => self::MENU_ENTRY_MACRO]
		]);

		if (!$macros) {
			return null;
		}

		return $macros[0];
	}

	private static function ensureDefaultPermissionOverview(): void {
		$existing = self::getLinkMacros(self::MACRO_PREFIX);
		if ($existing) {
			return;
		}

		$default = [
			'l' => (string) _('Permission overview'),
			't' => 'internal',
			'v' => 'custom.dashboard',
			'tr' => '_self'
		];

		$encoded = json_encode($default, JSON_UNESCAPED_SLASHES);
		if ($encoded === false) {
			return;
		}

		$max_length = DB::getFieldLength('globalmacro', 'value');
		if (strlen($encoded) > $max_length) {
			return;
		}

		API::UserMacro()->createGlobal([[
			'macro' => self::buildMacroName(1, self::MACRO_PREFIX),
			'value' => $encoded,
			'description' => self::MACRO_DESCRIPTION
		]]);
	}

	private static function decodeMacroLinks(array $macros): array {
		$links = [];
		foreach ($macros as $macro) {
			$data = json_decode($macro['value'] ?? '', true);
			if (!is_array($data)) {
				continue;
			}

			$label = trim((string)($data['l'] ?? $data['label'] ?? ''));
			$type_raw = $data['t'] ?? $data['type'] ?? 'external';
			$type = $type_raw === 'internal' ? 'internal' : 'external';
			$value = trim((string)($data['v'] ?? $data['c'] ?? $data['value'] ?? ''));
			$url = trim((string)($data['url'] ?? ''));
			$action = trim((string)($data['action'] ?? ''));
			$target_raw = $data['tr'] ?? $data['target'] ?? '_self';
			$target = $target_raw === '_blank' ? '_blank' : '_self';

			if ($value === '') {
				$value = ($type === 'internal') ? $action : $url;
			}

			if ($label === '' || $value === '') {
				continue;
			}

			$links[] = [
				'label' => $label,
				'type' => $type,
				'value' => $value,
				'target' => $target
			];
		}

		return $links;
	}

	private static function buildMacroName(int $index, string $prefix): string {
		return $prefix.$index.self::MACRO_SUFFIX;
	}
}
