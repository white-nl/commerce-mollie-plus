<?php

namespace white\commerce\mollie\plus\migrations;

use craft\db\Migration;
use craft\db\Query;

/**
 * m220704_100405_remove_supported_locales migration.
 */
class m220704_100405_remove_supported_locales extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $molliePlusGateways = (new Query())
            ->select(['id', 'settings'])
            ->where(['type' => 'white\\commerce\\mollie\\plus\\gateways\\Gateway'])
            ->from(['{{%commerce_gateways}}'])
            ->all();

        if (empty($molliePlusGateways)) {
            return true;
        }

        foreach ($molliePlusGateways as $molliePlusGateway) {
            $settings = json_decode($molliePlusGateway['settings']);
            unset($settings->supportedLocales);
            $this->update('{{%commerce_gateways}}', ['settings' => json_encode($settings)], ['id' => $molliePlusGateway['id']]);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m220704_100405_remove_supported_locales cannot be reverted.\n";
        return false;
    }
}
