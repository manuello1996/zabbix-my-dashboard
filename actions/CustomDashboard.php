<?php declare(strict_types=1);
namespace Modules\MyDashboard\Actions;

use CController;
use CControllerResponseData;
use CWebUser;
use API;

class CustomDashboard extends CController {
    public function init(): void {
		$this->disableCsrfValidation();
    }

    protected function checkInput(): bool {
        return true;
    }

    protected function checkPermissions(): bool {
        return true;
    }

    protected function doAction(): void {

    try {
        $userData = CWebUser::$data;  // Static access

        $userID     = $userData['userid'] ?? 'no_userid';
        $username   = $userData['username'] ?? $userData['alias'] ?? 'no_username';
        $name       = $userData['name']    ?? 'no_name';
        $surname    = $userData['surname'] ?? 'no_surname';
        $userip     = $userData['userip'] ?? 'no_userip';


        $userOutputFields = ['userid', 'name', 'surname'];
        if (array_key_exists('username', $userData) || !array_key_exists('alias', $userData)) {
            $userOutputFields[] = 'username';
        }
        else {
            $userOutputFields[] = 'alias';
        }

        $userDataAPI = API::User()->get([
            'userids'       => [$userID],
            'output'        => $userOutputFields,
            'selectUsrgrps' => ['usrgrpid','name'],
            'selectRole'    => ['roleid', 'name'],
            'selectMedias'  => ['mediatypeid', 'sendto', 'period', 'severity', 'active']
        ]);

        $displayName = trim("{$name} {$surname}") ?: $username;
        $groups = $userDataAPI[0]['usrgrps'] ?? [];
        $userGroupsNames = array_column($groups, 'name');
        $userRole = $userDataAPI[0]['role'] ?? [];
        $userRoleName = $userRole['name'] ?? null;

        $userMedia = $userDataAPI[0]['medias'] ?? [];

        $mediaTypeIds = [];
        foreach ($userMedia as $media) {
            if (isset($media['mediatypeid'])) {
                $mediaTypeIds[] = $media['mediatypeid'];
            }
        }
        $mediaTypeIds = array_values(array_unique($mediaTypeIds));

        $mediaTypeMap = [];
        if (!empty($mediaTypeIds)) {
            $mediaTypes = API::Mediatype()->get([
                'output' => ['mediatypeid', 'name', 'status', 'type'],
                'mediatypeids' => $mediaTypeIds
            ]);
            foreach ($mediaTypes as $mediaType) {
                $mediaTypeId = $mediaType['mediatypeid'] ?? null;
                if ($mediaTypeId === null) {
                    continue;
                }
                $mediaTypeMap[$mediaTypeId] = [
                    'name' => $mediaType['name'] ?? '',
                    'status' => (int)($mediaType['status'] ?? 0),
                    'type' => (int)($mediaType['type'] ?? 0)
                ];
            }
        }

        $userMediaDetails = [];
        foreach ($userMedia as $media) {
            $mediaTypeId = $media['mediatypeid'] ?? null;
            $sendTo = $media['sendto'] ?? [];
            $sendTo = is_array($sendTo) ? implode(', ', array_filter($sendTo)) : (string)$sendTo;

            $mediaType = $mediaTypeId !== null ? ($mediaTypeMap[$mediaTypeId] ?? []) : [];

            $userMediaDetails[] = [
                'mediatypeid' => $mediaTypeId,
                'name' => $mediaType['name'] ?? '',
                'mediatype_status' => $mediaType['status'] ?? null,
                'mediatype_type' => $mediaType['type'] ?? null,
                'sendto' => $sendTo,
                'period' => $media['period'] ?? '',
                'severity' => (int)($media['severity'] ?? 0),
                'active' => (int)($media['active'] ?? 0)
            ];
        }

        // 3. Get hostgroup permissions via usergroup.get
        $userGroups = API::Usergroup()->get([
            'userids'               => [$userID],
            'selectHostGroupRights' => ['id', 'permission']
        ]);

        $hostPerms = [];
        foreach ($userGroups as $ug) {
            foreach ($ug['hostgroup_rights'] as $hr) {
                $hgId = $hr['id'] ?? null;

                if ($hgId === null) {
                    continue;  // skip invalid entries
                }

                $permission = (int)$hr['permission'];

                // Retrieve hostgroup info (name) with each permission entry:
                $hgInfo = API::Hostgroup()->get([
                    'groupids' => [$hgId],
                    'output'   => ['name']
                ]);
                // hgInfo is an array; retrieve first result's name
                $hgName = $hgInfo[0]['name'] ?? null;

                // Use name even if the same group appears multiple times:
                if (!isset($hostPerms[$hgId]) || $permission > $hostPerms[$hgId]['permission']) {
                    $hostPerms[$hgId] = [
                        'permission' => $permission,
                        'name' => $hgName
                    ];
                    }
            }
        }

        // After fetching hostgroup names:
        $rw = [];
        $ro = [];
        $deny = [];
        foreach ($hostPerms as $hg) {
            $permission = $hg['permission'];      // Get the permission integer
            $name = $hg['name'];

            if ($permission === 3) {
                $rw[] = $name;  // read-write
            }
            elseif ($permission === 2) {
                $ro[] = $name;  // read-only
            }
            elseif ($permission === 0) {
                $deny[] = $name;  // deny-only
            }
        }

        // --- Services permissions (RO vs RW) ----------------------------------

        // 1) Services visible for current user (include parents)
        $servicesAll = API::Service()->get([
            'output' => ['serviceid', 'name'],
            'selectParents' => ['serviceid'],   // returns parents array if any
            'preservekeys' => true
        ]);

        // 2) Services editable for current user (RW) (include parents)
        $servicesEditable = API::Service()->get([
            'output' => ['serviceid', 'name'],
            'selectParents' => ['serviceid'],
            'editable' => true,
            'preservekeys' => true
        ]);

        // Defensive: API may return false on error
        if (!is_array($servicesAll)) {
            $servicesAll = [];
        }
        if (!is_array($servicesEditable)) {
            $servicesEditable = [];
        }

        // 3) Filter TOP LEVEL only: no parents
        $servicesAllTop = [];
        foreach ($servicesAll as $sid => $svc) {
            $parents = $svc['parents'] ?? [];
            if (empty($parents)) {
                $servicesAllTop[$sid] = $svc;
            }
        }

        $servicesEditableTop = [];
        foreach ($servicesEditable as $sid => $svc) {
            $parents = $svc['parents'] ?? [];
            if (empty($parents)) {
                $servicesEditableTop[$sid] = $svc;
            }
        }

        // 4) Build RW/RO lists from top level only
        $services_rw = [];
        $services_ro = [];

        foreach ($servicesAllTop as $sid => $svc) {
            $svcName = $svc['name'] ?? null;
            if ($svcName === null) {
                continue;
            }

            if (array_key_exists($sid, $servicesEditableTop)) {
                $services_rw[] = $svcName;
            }
            else {
                $services_ro[] = $svcName;
            }
        }

        $data = [
            'page_title'        => "{$username} - Zabbix Personal Dashboard",
            'title'             => "Hello {$displayName}", // dynamic
            'message'           => "Welcome to your dashboard! Here you can see all the permissions you have.",
            'user_groups'       => $userGroupsNames,
            'userRoleName'      => $userRoleName,
            'userip'            => $userip,
            'userMedia'         => $userMediaDetails,
            'ro_groups'         => $ro,
            'rw_groups'         => $rw,
            'deny_groups'       => $deny,
            'raw_userGroups'    => json_encode($hostPerms, JSON_PRETTY_PRINT),
            'raw_userRole'      => json_encode($userRole, JSON_PRETTY_PRINT),
            'raw_user_data'     => json_encode($userData, JSON_PRETTY_PRINT),
            'services_ro'       => $services_ro,
            'services_rw'       => $services_rw
        ];

        $this->setResponse(new CControllerResponseData($data));

    }
    catch (\Throwable $e) {
        $data = ['error-message' => $e->getMessage()];
        $this->setResponse(new CControllerResponseData($data));
    }

    }

}
