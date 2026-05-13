<?php

namespace SampleProject;

class ArrayDimFetchPresenter
{
    public function renderDefault($translator): void
    {
        $form = [
            'messageField' => [
                'field' => [
                    'title' => 'feedback',
                ],
            ],
            'mainSection' => [
                'title' => 'modal',
            ],
        ];

        $messageField = $form['messageField'];
        $section = $form['mainSection'];

        $translator->translate('ebox.in_app_rating.field.title.' . $messageField['field']['title']);
        $translator->translate('ebox.in_app_rating.title.' . $section['title']);
    }
}
