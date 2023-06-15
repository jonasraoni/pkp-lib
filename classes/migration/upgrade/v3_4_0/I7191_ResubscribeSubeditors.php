<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I7191_ResubscribeSubeditors.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I7191_ResubscribeSubeditors
 *
 * @brief Resubscribe subeditors to certain email notifications.
 *
 *   In previous versions, subeditors received two emails
 *   with a new submission: NOTIFICATION_TYPE_SUBMISSION_SUBMITTED and a copy
 *   of the email sent to the author. The NOTIFICATION_TYPE_SUBMISSION_SUBMITTED
 *   email is more appropriate, but they could not opt out of the author copy, so
 *   many editors unsubscribed from the NOTIFICATION_TYPE_SUBMISSION_SUBMITTED
 *   email.
 *
 *   In 3.4, this was fixed so that subeditors will not receive two emails.
 *   However, to ensure they continue receiving at least one email, we need to
 *   resubscribe them to the NOTIFICATION_TYPE_SUBMISSION_SUBMITTED email. If
 *   they want, they can unsubscribe again.
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PKP\install\DowngradeNotSupportedException;

class I7191_ResubscribeSubeditors extends \PKP\migration\Migration
{
    /**
     * PKP\notification\Notification::NOTIFICATION_TYPE_SUBMISSION_SUBMITTED
     */
    public const NOTIFICATION_TYPE = 0x1000001;

    /**
     * PKP\security\Role::ROLE_ID_SUB_EDITOR
     */
    public const SUBEDITOR_ROLE = 0x00000011;

    public function up(): void
    {
        DB::table('notification_subscription_settings as nss')
            ->join('user_user_groups as uug', 'nss.user_id', '=', 'uug.user_id')
            ->join('user_groups as ug', fn (JoinClause $join) =>
                $join->on('ug.user_group_id', '=', 'uug.user_group_id')
                    ->on('ug.context_id', '=', 'nss.context')
                    ->where('ug.role_id', '=', self::SUBEDITOR_ROLE)
            )
            ->where('nss.setting_name', 'blocked_emailed_notification')
            ->where('nss.setting_value', self::NOTIFICATION_TYPE)
            ->delete();
    }

    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}
