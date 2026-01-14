<?php declare(strict_types=1);
/** @var CView $this */
/** @var array $data */

$page = new CHtmlPage();
$page->setTitle($data['page_title'] ?? _('External links'));

$form = (new CForm())
	->setId('external-links-form')
	->setName('external_links_form')
	->setAttribute('aria-labelledby', CHtmlPage::PAGE_TITLE_ID)
	->addItem((new CVar('action', 'external.links.update'))->removeId())
	->addItem((new CVar(CSRF_TOKEN_NAME, $data['csrf_token']))->removeId());

$links = $data['links'] ?? [];
if (!$links) {
	$links = [['label' => '', 'type' => 'external', 'value' => '', 'target' => '_self']];
}
$menu_entry_value = (string)($data['menu_entry_value'] ?? '');
$index = 0;

$table = (new CTable())
	->setId('external-links-table')
	->addClass(ZBX_STYLE_LIST_TABLE)
	->addClass('external-links-table')
	->setAttribute('style', 'width: 100%;')
	->setHeader([_('Order'), _('Label'), _('Type'), _('URL or action'), _('Open in new tab'), _('Actions')]);

$index = 0;
foreach ($links as $link) {
	$label = (string)($link['label'] ?? '');
	$type = (($link['type'] ?? 'external') === 'internal') ? 'internal' : 'external';
	$value = (string)($link['value'] ?? '');
	$target_blank = ($link['target'] ?? '_self') === '_blank';

	$label_input = (new CTextBox('links['.$index.'][label]', $label))
		->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
		->setAttribute('maxlength', '64')
		->setAttribute('data-field', 'label');
	$type_select = (new CSelect('links['.$index.'][type]'))
		->addOptions([
			new CSelectOption('external', _('External')),
			new CSelectOption('internal', _('Internal'))
		])
		->setValue($type)
		->setAttribute('data-field', 'type');
	$value_input = (new CTextBox('links['.$index.'][value]', $value))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setAttribute('placeholder', $type === 'internal' ? 'userprofile.edit' : 'https://example.com')
		->setAttribute('data-field', 'value');
	$target_input = (new CCheckBox('links['.$index.'][target_blank]'))
		->setChecked($target_blank)
		->setUncheckedValue(0)
		->setAttribute('data-field', 'target_blank');
	$move_up = (new CButtonLink(_('▲')))
		->addClass('link-action custom-arrow')
		->setAttribute('data-action', 'move-up');
	$move_down = (new CButtonLink(_('▼')))
		->addClass('link-action custom-arrow')
		->setAttribute('data-action', 'move-down');
	$remove_link = (new CButtonLink(_('Remove')))
		->addClass('link-action')
		->setAttribute('data-action', 'remove-external-link');

	$table->addRow([
		(new CDiv([$move_up, ' ', $move_down]))->addClass('external-links-order'),
		$label_input,
		$type_select,
		$value_input,
		$target_input,
		$remove_link
	], null, 'external-links-row');

	$index++;
}

$controls = (new CDiv([
	(new CButtonLink(_('Add')))
		->setId('external-links-add')
]))->addClass('external-links-controls');

$form->addItem(
	(new CDiv([
		(new CTable())
			->addClass(ZBX_STYLE_LIST_TABLE)
			->setHeader([_('Menu entry label'), _('Value')])
			->addRow([
				(new CCol(_('My Zabbix Home')))->addClass(ZBX_STYLE_NOWRAP),
				(new CTextBox('menu_entry', $menu_entry_value))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					->setAttribute('placeholder', _('My Zabbix Home'))
			]),
		$table,
		$controls,
		(new CDiv([
			_('External links use full URLs. Internal links use Zabbix action names.'),
			new CTag('br'),
			_('Example external: https://example.com'),
			new CTag('br'),
			_('Example internal: userprofile.edit')
		]))->addClass('external-links-help')
	]))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
);

$macro_entries = $data['macro_entries'] ?? [];
if ($macro_entries) {
	$macro_table = (new CTable())
		->addClass(ZBX_STYLE_LIST_TABLE)
		->setHeader([_('Macro'), _('Value')]);

	foreach ($macro_entries as $entry) {
		$macro_table->addRow([
			(new CCol($entry['macro'] ?? ''))->addClass(ZBX_STYLE_NOWRAP),
			(new CCol($entry['value'] ?? ''))
		]);
	}

	#$form->addItem(
	#	(new CDiv([
	#			(new CTag('h4', true, _('Stored macros')))->addClass(ZBX_STYLE_HEADER_TITLE),
	#			$macro_table
	#		]))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
	#);
}

$footer = makeFormFooter(
	(new CSubmitButton(_('Update')))
);
$footer->addClass('external-links-footer');
$form->addItem($footer);

$page->addItem($form);
show_messages();
$page->show();

(new CScriptTag('
(function() {
	var table = document.getElementById("external-links-table");
	var form = document.getElementById("external-links-form");
	var addButton = document.getElementById("external-links-add");
	var removeLabel = '.json_encode(_('Remove')).';
	var upLabel = '.json_encode(_('Up')).';
	var downLabel = '.json_encode(_('Down')).';
	var typeExternal = '.json_encode(_('External')).';
	var typeInternal = '.json_encode(_('Internal')).';
	var placeholderExternal = '.json_encode("https://example.com").';
	var placeholderInternal = '.json_encode("userprofile.edit").';
	var valueSelector = "[data-field=\"value\"]";
	var labelSelector = "[data-field=\"label\"]";
	var typeSelector = "[data-field=\"type\"]";
	var targetSelector = "[data-field=\"target_blank\"]";
	var upSelector = '.json_encode("a[data-action=\"move-up\"]").';
	var downSelector = '.json_encode("a[data-action=\"move-down\"]").';

	function getRows() {
		var allRows = (table.tBodies && table.tBodies.length > 0)
			? table.tBodies[0].querySelectorAll("tr")
			: table.querySelectorAll("tr");
		var rows = [];
		Array.prototype.forEach.call(allRows, function(row) {
			if (row.querySelector("[data-field]")) {
				rows.push(row);
			}
		});
		return rows;
	}

	function addRow(label, type, value, targetBlank) {
		var index = getRows().length;
		var row = document.createElement("tr");
		row.className = "external-links-row";

		row.innerHTML =
			"<td class=\\"external-links-order\\">" +
				"<a href=\\"#\\" class=\\"link-action\\" data-action=\\"move-up\\">" + upLabel + "</a> " +
				"<a href=\\"#\\" class=\\"link-action\\" data-action=\\"move-down\\">" + downLabel + "</a>" +
			"</td>" +
			"<td><input type=\\"text\\" class=\\"text\\" maxlength=\\"64\\" data-field=\\"label\\" name=\\"links[" + index + "][label]\\" value=\\"" + label + "\\"></td>" +
			"<td><select data-field=\\"type\\" name=\\"links[" + index + "][type]\\">" +
				"<option value=\\"external\\">" + typeExternal + "</option>" +
				"<option value=\\"internal\\">" + typeInternal + "</option>" +
			"</select></td>" +
			"<td><input type=\\"text\\" class=\\"text\\" data-field=\\"value\\" name=\\"links[" + index + "][value]\\" value=\\"" + value + "\\"></td>" +
			"<td><input type=\\"checkbox\\" data-field=\\"target_blank\\" name=\\"links[" + index + "][target_blank]\\" " + (targetBlank ? "checked" : "") + "></td>" +
			"<td><a href=\\"#\\" class=\\"link-action\\" data-action=\\"remove-external-link\\">" + removeLabel + "</a></td>";

		row.querySelector("select").value = type;
		updateRowState(row);

		if (table.tBodies && table.tBodies.length > 0) {
			table.tBodies[0].appendChild(row);
		}
		else {
			table.appendChild(row);
		}
		renumberRows();
	}

	function updateRowState(row) {
		var typeSelect = row.querySelector("select");
		var valueInput = row.querySelector(valueSelector);
		var isInternal = typeSelect && typeSelect.value === "internal";

		if (valueInput) {
			valueInput.placeholder = isInternal ? placeholderInternal : placeholderExternal;
		}
	}

	function renumberRows() {
		var rows = getRows();
		rows.forEach(function(row, index) {
			if (!row.classList.contains("external-links-row")) {
				row.classList.add("external-links-row");
			}
			var up = row.querySelector(upSelector);
			var down = row.querySelector(downSelector);
			if (up) {
				up.style.visibility = index === 0 ? "hidden" : "visible";
			}
			if (down) {
				down.style.visibility = index === rows.length - 1 ? "hidden" : "visible";
			}
			row.querySelectorAll("[data-field]").forEach(function(input) {
				var field = input.getAttribute("data-field");
				if (!field) {
					return;
				}
				input.setAttribute("name", "links[" + index + "][" + field + "]");
			});
		});
	}

	addButton.addEventListener("click", function(event) {
		event.preventDefault();
		addRow("", "external", "", false);
	});

	table.addEventListener("click", function(event) {
		var target = event.target;
		if (target && target.getAttribute("data-action") === "remove-external-link") {
			event.preventDefault();
			var row = target.closest("tr");
			if (row) {
				row.remove();
				renumberRows();
			}
		}
		if (target && target.getAttribute("data-action") === "move-up") {
			event.preventDefault();
			var row = target.closest("tr");
			if (row && row.previousElementSibling) {
				row.parentNode.insertBefore(row, row.previousElementSibling);
				renumberRows();
			}
		}
		if (target && target.getAttribute("data-action") === "move-down") {
			event.preventDefault();
			var row = target.closest("tr");
			if (row && row.nextElementSibling) {
				row.parentNode.insertBefore(row.nextElementSibling, row);
				renumberRows();
			}
		}
	});

	table.addEventListener("change", function(event) {
		if (event.target && event.target.tagName === "SELECT") {
			updateRowState(event.target.closest("tr"));
		}
	});

	form.addEventListener("submit", function() {
		renumberRows();
	});

	getRows().forEach(updateRowState);
	renumberRows();
})();
'))->setOnDocumentReady()->show();
