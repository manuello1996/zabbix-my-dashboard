<?php declare(strict_types=1);

namespace Modules\MyDashboard;

use CProfile;
use CHtmlUrlValidator;
use DB;

final class MyDashboardUserLinks {
	private const PROFILE_LINKS = 'mydashboard-user-links';
	private const PROFILE_MENU_ENTRY = 'mydashboard-user-menu-entry';

	public static function getLinks(): array {
		$raw = CProfile::get(self::PROFILE_LINKS, '');
		if ($raw === '') {
			return [];
		}

		$data = json_decode($raw, true);
		if (!is_array($data)) {
			return [];
		}

		return self::decodeLinks($data);
	}

	public static function getMenuEntryLabel(): string {
		$value = self::getMenuEntryValue();
		return $value !== '' ? $value : _('User Link');
	}

	public static function getMenuEntryValue(): string {
		return trim((string) CProfile::get(self::PROFILE_MENU_ENTRY, ''));
	}

	public static function saveMenuEntryValue(string $value, array &$errors): bool {
		$value = trim($value);
		if ($value === '') {
			CProfile::delete(self::PROFILE_MENU_ENTRY);
			return true;
		}

		$max_length = DB::getFieldLength('profiles', 'value_str');
		if (strlen($value) > $max_length) {
			$errors[] = _s('Menu entry exceeds the maximum size of %1$d characters.', $max_length);
			return false;
		}

		CProfile::update(self::PROFILE_MENU_ENTRY, $value, PROFILE_TYPE_STR);

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

		if (!$compact) {
			CProfile::delete(self::PROFILE_LINKS);
			return true;
		}

		$encoded = json_encode($compact, JSON_UNESCAPED_SLASHES);
		if ($encoded === false) {
			$errors[] = _('Failed to encode external links.');
			return false;
		}

		$max_length = DB::getFieldLength('profiles', 'value_str');
		if (strlen($encoded) > $max_length) {
			$errors[] = _s('External links exceed the maximum size of %1$d characters.', $max_length);
			return false;
		}

		CProfile::update(self::PROFILE_LINKS, $encoded, PROFILE_TYPE_STR);

		return true;
	}

	private static function decodeLinks(array $entries): array {
		$links = [];
		foreach ($entries as $data) {
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
}
