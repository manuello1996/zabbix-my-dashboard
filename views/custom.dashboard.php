<?php declare(strict_types=1);
/** @var CView $this */
/** @var array  $data */

$pageTitle = $data['page_title'] ?? _('Custom Dashboard');
$page = new CHtmlPage();


$page->setTitle($pageTitle)
     ->setControls(
         (new CDiv())
            ->addItem(
                 new CTag('span', true, _('Role: ') . $data['userRoleName'])
             )
            ->addItem(
                 (new CButton('refresh', _('Refresh')))
                     ->onClick('location.reload();')
                     ->addClass('btn')
             )
            ->setAttribute('class', 'header-role')
     );




if (empty($data['error-message'])) {

    // Zabbix-style widget container
    $widget = (new CDiv())->addClass('container');

    // Header block
    $header = (new CDiv())->addClass('dashboard-header');
    $header->addItem(new CTag('h1', true, _($data['title'])));

    // Body block
    $body = (new CDiv())->addClass('dashboard-body');

    // Top Contnent
    $topContainer = new CDiv();
    $topContainer->setAttribute('class', 'topContainer');
    $topContainer->addItem(new CTag('h2', true, _($data['message'])));

    $topContainerWrapper = new CDiv();
    $topContainerWrapper->setAttribute('class', 'topContainerWrapper');

    $topContainerLeft = new CDiv();
    $topContainerLeft->setAttribute('class', 'topContainerLeft');
    $topContainerLeft->addItem(new CTag('h4', true, _('User details')));

    renderJsonTable(_(''), $data['raw_user_data'], ['#', 'Value'], ['username', 'name', 'surname'], $topContainerLeft);

    $topContainerLeft->addItem(new CTag('h4', true, _('Media')));
    renderMediaTableZabbixStyle($data['userMedia'] ?? [], $topContainerLeft);
    $topContainerWrapper->addItem($topContainerLeft);

    $topContainerRight = new CDiv();
    $topContainerRight->setAttribute('class', 'topContainerRight');
    $topContainerRight->addItem(new CTag('h4', true, _('Your User Groups')));


    // User Groups

    // Common wrapper style for each column
    $groupColumnStyle = 'width: 100%; flex: 1; padding: 0 10px; box-sizing: border-box;';

    // Wrap each column in a flex container
    $groupContainer = new CDiv();
    $groupContainer->setAttribute('style', 'display: flex; justify-content: space-between; flex-wrap: wrap; width: 33%;');

    // RW column
    $group_column = new CDiv();
    $group_column->setAttribute('style', $groupColumnStyle);

    if (empty($data['user_groups'])) {
        $group_column->addItem(new CDiv(_("You don't belong to any user groups.")));
    } else {
        $group_tags = array_map(fn($g) => ['id' => $g, 'name' => $g], array_filter($data['user_groups']));
        $group_multi = new CMultiSelect([
            'name' => 'user_groups[]',
            'object_name' => 'usersGroups',
            'data' => $group_tags,
            'readonly' => true
        ]);
        $group_column->addItem($group_multi);
    }

    $topContainerRight->addItem($group_column);

    $topContainerWrapper->addItem($topContainerRight);
    $topContainer->addItem($topContainerWrapper);
    $body->addItem($topContainer);


    // Common wrapper style for each column
    $hostGroupColumnStyle = 'width: 100%; flex: 1; padding: 0 10px; box-sizing: border-box;';

    // Wrap each column in a flex container
    $hostGroupColumnsContainer = new CDiv();
    $hostGroupColumnsContainer->setAttribute('style', 'display: flex; justify-content: space-between; flex-wrap: wrap; width: 100%;');

    // RW column
    $rw_column = new CDiv();
    $rw_column->setAttribute('style', $hostGroupColumnStyle);
    $rw_column->addItem(new CTag('h4', true, _('Read/Write Host Groups')));

    if (empty($data['rw_groups'])) {
        $rw_column->addItem(new CDiv(_('No group with Read-Write permission')));
    } else {
        $rw_group_tags = array_map(fn($g) => ['id' => $g, 'name' => $g], array_filter($data['rw_groups']));
        $rw_multi = new CMultiSelect([
            'name' => 'rw_groups[]',
            'object_name' => 'hostGroupsRW',
            'data' => $rw_group_tags,
            'readonly' => true
        ]);
        $rw_column->addItem($rw_multi);
    }

    // RO column
    $ro_column = new CDiv();
    $ro_column->setAttribute('style', $hostGroupColumnStyle);
    $ro_column->addItem(new CTag('h4', true, _('Read-Only Host Groups')));

    if (empty($data['ro_groups'])) {
        $ro_column->addItem(new CDiv(_('No group with Read-Only permission')));
    } else {
        $ro_group_tags = array_map(fn($g) => ['id' => $g, 'name' => $g], array_filter($data['ro_groups']));
        $ro_multi = new CMultiSelect([
            'name' => 'ro_groups[]',
            'object_name' => 'hostGroupsRO',
            'data' => $ro_group_tags,
            'readonly' => true
        ]);
        $ro_column->addItem($ro_multi);
    }

    // Deny column
    $deny_column = new CDiv();
    $deny_column->setAttribute('style', $hostGroupColumnStyle);
    $deny_column->addItem(new CTag('h4', true, _('Deny Host Groups')));

    if (empty($data['deny_groups'])) {
        $deny_column->addItem(new CDiv(_('No group with Deny permission')));
    } else {
        $deny_group_tags = array_map(fn($g) => ['id' => $g, 'name' => $g], array_filter($data['deny_groups']));
        $deny_multi = new CMultiSelect([
            'name' => 'deny_groups[]',
            'object_name' => 'hostGroupsDENY',
            'data' => $deny_group_tags,
            'readonly' => true
        ]);
        $deny_column->addItem($deny_multi);
    }

    // Assemble layout
    $hostGroupColumnsContainer->addItem($rw_column);
    $hostGroupColumnsContainer->addItem($ro_column);
    $hostGroupColumnsContainer->addItem($deny_column);
    $body->addItem($hostGroupColumnsContainer);


    // ------------------------------------------------------
    // Services (RW / RO) like Hostgroups
    // ------------------------------------------------------

    // Common wrapper style for each column
    $serviceColumnStyle = 'width: 100%; flex: 1; padding: 0 10px; box-sizing: border-box;';

    // Wrap each column in a flex container
    $serviceColumnsContainer = new CDiv();
    $serviceColumnsContainer->setAttribute('style', 'display: flex; justify-content: space-between; flex-wrap: wrap; width: 100%;margin-top:20px');

    // RW Services column
    $svc_rw_column = new CDiv();
    $svc_rw_column->setAttribute('style', $serviceColumnStyle);
    $svc_rw_column->addItem(new CTag('h4', true, _('Read/Write Services - Top level only')));

    if (empty($data['services_rw'])) {
        $svc_rw_column->addItem(new CDiv(_('No services with Read-Write permission')));
    } else {
        $svc_rw_tags = array_map(
            fn($g) => ['id' => (string)$g, 'name' => (string)$g],
            array_filter($data['services_rw'])
        );

        $svc_rw_multi = new CMultiSelect([
            'name' => 'services_rw[]',
            'object_name' => 'servicesRW',
            'data' => $svc_rw_tags,
            'readonly' => true
        ]);

        $svc_rw_column->addItem($svc_rw_multi);
    }

    // RO Services column
    $svc_ro_column = new CDiv();
    $svc_ro_column->setAttribute('style', $serviceColumnStyle);
    $svc_ro_column->addItem(new CTag('h4', true, _('Read-Only Services - Top level only')));

    if (empty($data['services_ro'])) {
        $svc_ro_column->addItem(new CDiv(_('No services with Read-Only permission')));
    } else {
        $svc_ro_tags = array_map(
            fn($g) => ['id' => (string)$g, 'name' => (string)$g],
            array_filter($data['services_ro'])
        );

        $svc_ro_multi = new CMultiSelect([
            'name' => 'services_ro[]',
            'object_name' => 'servicesRO',
            'data' => $svc_ro_tags,
            'readonly' => true
        ]);

        $svc_ro_column->addItem($svc_ro_multi);
    }

    // Assemble layout
    $serviceColumnsContainer->addItem($svc_rw_column);
    $serviceColumnsContainer->addItem($svc_ro_column);
    $body->addItem($serviceColumnsContainer);

   // $body->addItem(new CTag('pre', true, $data['raw_userRole']));
   // $body->addItem(new CDiv($data['raw_userGroups']));
   // $body->addItem(new CTag('pre', true, $data['raw_user_data']));
   // $body->addItem(new CTag('hr', true, _(' ')));



    // Final assembly
    $widget->addItem($header);
    $widget->addItem($body);
    $page->addItem($widget);
} else {
    $page->addItem(new CDiv($data['error-message']));
}

$page->show();


function renderJsonTable(string $title, string $json, array $columns, array $keys, CTag $target): void {
	$data = json_decode($json, true);

	// Section title
	$target->addItem(new CTag('h2', true, $title));

	if (!is_array($data)) {
		$target->addItem(new CDiv(_('Failed to parse JSON')));
		return;
	}

	// Table with custom column titles
	$table = (new CTableInfo())
		->setHeader($columns);

	foreach ($keys as $key) {
		if (!array_key_exists($key, $data)) {
			continue;
		}

		$value = $data[$key];

		if (is_array($value)) {
			$value = new CTag('pre', true, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
		} elseif (is_bool($value)) {
			$value = $value ? _('true') : _('false');
		} elseif ($value === '') {
			$value = '-';
		}

		$table->addRow([$key, $value]);
	}

	$target->addItem($table);
}

function renderMediaTableZabbixStyle(array $medias, CTag $target): void {
	if (empty($medias)) {
		$target->addItem(new CDiv(_('No media configured.')));
		return;
	}

	$table = (new CTable())
		->addClass(ZBX_STYLE_LIST_TABLE)
		->setId('media-table')
		->setAttribute('style', 'width: 100%;border: 0;')
		->setHeader([_('Type'), _('Send to'), _('When active'), _('Use if severity'), _('Status')]);

	foreach ($medias as $media) {
		$mediaTypeName = trim((string)($media['name'] ?? ''));
		$mediaTypeStatus = $media['mediatype_status'] ?? null;

		if ($mediaTypeName === '') {
			$mediaName = (new CSpan(_('Unknown')))->addClass(ZBX_STYLE_DISABLED);
			$status = (new CSpan(_('Disabled')))->addClass(ZBX_STYLE_RED);
		}
		elseif ($mediaTypeStatus === MEDIA_TYPE_STATUS_ACTIVE) {
			$mediaName = $mediaTypeName;
			$status = ((int)($media['active'] ?? 0) === MEDIA_STATUS_ACTIVE)
				? (new CSpan(_('Enabled')))->addClass(ZBX_STYLE_GREEN)
				: (new CSpan(_('Disabled')))->addClass(ZBX_STYLE_RED);
		}
		else {
			$mediaName = [
				new CSpan($mediaTypeName),
				makeWarningIcon(_('Media type disabled by Administration.'))
			];
			$status = (new CSpan(_('Disabled')))->addClass(ZBX_STYLE_RED);
		}

		$sendTo = $media['sendto'] ?? '';
		if (is_array($sendTo)) {
			$sendTo = implode(', ', $sendTo);
		}
		$sendTo = (string)$sendTo;

		if (mb_strlen($sendTo) > 50) {
			$sendTo = (new CSpan(mb_substr($sendTo, 0, 50).'...'))->setHint($sendTo);
		}

		$mediaSeverity = [];
		for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
			$severityName = CSeverityHelper::getName($severity);
			$isActive = ((int)($media['severity'] ?? 0) & (1 << $severity));
			$mediaSeverity[$severity] = (new CSpan(mb_substr($severityName, 0, 1)))
				->setHint($severityName.' ('.($isActive ? _('on') : _('off')).')', '', false)
				->addClass($isActive
					? CSeverityHelper::getStatusStyle($severity)
					: ZBX_STYLE_STATUS_DISABLED
				);
		}

		$period = (string)($media['period'] ?? '');
		if ($period === '') {
			$period = '-';
		}

		$table->addRow([
			$mediaName,
			$sendTo !== '' ? $sendTo : '-',
			(new CDiv($period))
				->setAttribute('style', 'max-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
				->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS),
			(new CDiv($mediaSeverity))->addClass(ZBX_STYLE_STATUS_CONTAINER),
			$status
		]);
	}

	$target->addItem(
		(new CDiv($table))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	);
}
