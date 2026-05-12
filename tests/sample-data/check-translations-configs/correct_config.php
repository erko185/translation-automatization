<?php

use Efabrica\TranslationsAutomatization\Command\CheckDictionaries\CheckDictionariesConfig;

return new CheckDictionariesConfig([
    'en_US' => [
        'checkout.summary.title' => 'Summary %count%',
        'checkout.payment.label' => 'Payment %count%',
        'feed.condition.option.a' => 'Condition A %count%',
        'feed.condition.option.b' => 'Condition B %count%',
        'program.template.title' => 'Program template %count%',
        'latte.dynamic.key' => 'Latte %count%',
        'onair.app.admin_module.presenters.show_presenter.edit.title' => 'Edit %title%',
        'unused' => 'Unused %count%',
    ],
]);
