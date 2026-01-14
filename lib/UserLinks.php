<?php declare(strict_types=1);

namespace Modules\MyDashboard;

use CProfile;
use CHtmlUrlValidator;
use DB;

final class MyDashboardUserLinks {
	private const PROFILE_LINKS = 'mydashboard-user-links';
	private const PROFILE_LINKS_COUNT = 'mydashboard-user-links-count';
	private const PROFILE_LINKS_CHUNK_FORMAT = 'mydashboard-user-links-%d';
	private const PROFILE_MENU_ENTRY = 'mydashboard-user-menu-entry';

	public static function getLinks(): array {
		$count = (int) CProfile::get(self::PROFILE_LINKS_COUNT, '0');
		if ($count > 0) {
			$entries = [];
			for ($i = 1; $i <= $count; $i++) {
				$key = sprintf(self::PROFILE_LINKS_CHUNK_FORMAT, $i);
				$raw = CProfile::get($key, '');
				if ($raw === '') {
					continue;
				}
				$data = json_decode($raw, true);
				if (is_array($data)) {
					$entries = array_merge($entries, $data);
				}
			}

			return self::decodeLinks($entries);
		}

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

		$existing_count = (int) CProfile::get(self::PROFILE_LINKS_COUNT, '0');
		if ($existing_count > 0) {
			for ($i = 1; $i <= $existing_count; $i++) {
				CProfile::delete(sprintf(self::PROFILE_LINKS_CHUNK_FORMAT, $i));
			}
		}
		CProfile::delete(self::PROFILE_LINKS);
		CProfile::delete(self::PROFILE_LINKS_COUNT);

		if (!$compact) {
			return true;
		}

		$max_length = DB::getFieldLength('profiles', 'value_str');

		$chunks = self::encodeChunks($compact, $max_length, $errors);
		if ($chunks === null) {
			return false;
		}

		foreach ($chunks as $index => $encoded) {
			CProfile::update(sprintf(self::PROFILE_LINKS_CHUNK_FORMAT, $index + 1), $encoded, PROFILE_TYPE_STR);
		}
		CProfile::update(self::PROFILE_LINKS_COUNT, (string) count($chunks), PROFILE_TYPE_STR);

		return true;
	}

	public static function getStoredValues(): array {
		$values = [];

		$single = CProfile::get(self::PROFILE_LINKS, '');
		if ($single !== '') {
			$values[self::PROFILE_LINKS] = $single;
		}

		$count = (int) CProfile::get(self::PROFILE_LINKS_COUNT, '0');
		if ($count > 0) {
			$values[self::PROFILE_LINKS_COUNT] = (string) $count;
			for ($i = 1; $i <= $count; $i++) {
				$key = sprintf(self::PROFILE_LINKS_CHUNK_FORMAT, $i);
				$value = CProfile::get($key, '');
				if ($value !== '') {
					$values[$key] = $value;
				}
			}
		}

		$menu_entry = CProfile::get(self::PROFILE_MENU_ENTRY, '');
		if ($menu_entry !== '') {
			$values[self::PROFILE_MENU_ENTRY] = $menu_entry;
		}

		return $values;
	}

	private static function encodeChunks(array $links, int $max_length, array &$errors): ?array {
		$chunks = [];
		$current = [];

		foreach ($links as $entry) {
			$candidate = array_merge($current, [$entry]);
			$encoded = json_encode($candidate, JSON_UNESCAPED_SLASHES);
			if ($encoded === false) {
				$errors[] = _('Failed to encode external links.');
				return null;
			}

			if (strlen($encoded) > $max_length) {
				if (!$current) {
					$errors[] = _s('External links exceed the maximum size of %1$d characters.', $max_length);
					return null;
				}

				$final = json_encode($current, JSON_UNESCAPED_SLASHES);
				if ($final === false) {
					$errors[] = _('Failed to encode external links.');
					return null;
				}
				$chunks[] = $final;

				$current = [$entry];
				$single = json_encode($current, JSON_UNESCAPED_SLASHES);
				if ($single === false) {
					$errors[] = _('Failed to encode external links.');
					return null;
				}
				if (strlen($single) > $max_length) {
					$errors[] = _s('External links exceed the maximum size of %1$d characters.', $max_length);
					return null;
				}
			}
			else {
				$current = $candidate;
			}
		}

		if ($current) {
			$final = json_encode($current, JSON_UNESCAPED_SLASHES);
			if ($final === false) {
				$errors[] = _('Failed to encode external links.');
				return null;
			}
			$chunks[] = $final;
		}

		return $chunks;
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
