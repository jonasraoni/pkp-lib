<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I8929_AddForeignKeys.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I8929_AddForeignKeys
 *
 * @brief Describe upgrade/downgrade operations for introducing foreign key definitions to existing database relationships.
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\install\DowngradeNotSupportedException;

abstract class I8929_AddForeignKeys extends \PKP\migration\Migration
{
    abstract protected function getContextTable(): string;
    abstract protected function getContextSettingsTable(): string;
    abstract protected function getContextKeyField(): string;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('filters', function (Blueprint $table) {
            $table->bigInteger('context_id')->nullable()->default(null)->change();
            // Only needed to drop default 0
            $table->bigInteger('filter_group_id')->default(null)->change();
            $table->bigInteger('parent_filter_id')->nullable()->default(null)->change();
        });
        DB::table('filters')->where('context_id', '=', 0)->update(['context_id' => null]);
        DB::table('filters')->where('parent_filter_id', '=', 0)->update(['parent_filter_id' => null]);
        Schema::table('filters', function (Blueprint $table) {
            $table->foreign('context_id', 'filters_context_id')->references($this->getContextKeyField())->on($this->getContextTable())->onDelete('cascade');
            $table->foreign('parent_filter_id', 'filters_parent_filter_id')->references('filter_id')->on('filters')->onDelete('cascade');
        });

        Schema::table(
            'navigation_menu_item_assignments',
            fn (Blueprint $table) => $table->bigInteger('parent_id')->nullable()->default(null)->change()
        );
        DB::table('navigation_menu_item_assignments')->where('parent_id', '=', 0)->update(['parent_id' => null]);
        Schema::table(
            'navigation_menu_item_assignments',
            fn (Blueprint $table) => $table->foreign('parent_id', 'navigation_menu_item_assignments_parent_id')->references('navigation_menu_item_assignment_id')->on('navigation_menu_item_assignments')->onDelete('cascade')
        );

        foreach (['navigation_menu_items', 'navigation_menus', 'plugin_settings', 'user_groups'] as $tableName) {
            Schema::table($tableName, fn (Blueprint $table) => $table->bigInteger('context_id')->nullable()->default(null)->change());
            DB::table($tableName)->where('context_id', '=', 0)->update(['context_id' => null]);
            Schema::table(
                $tableName,
                fn (Blueprint $table) => $table->foreign('context_id', "{$tableName}_context_id")
                    ->references($this->getContextKeyField())
                    ->on($this->getContextTable())->onDelete('cascade')
            );
        }
        Schema::table('email_log', fn (Blueprint $table) => $table->bigInteger('sender_id')->nullable()->default(null)->change());
        DB::table('email_log')->where('sender_id', '=', 0)->update(['sender_id' => null]);
        Schema::table(
            'email_log',
            fn (Blueprint $table) => $table->foreign('sender_id', 'email_log_sender_id')->references('user_id')->on('users')->onDelete('cascade')
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}
