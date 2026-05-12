<?php

namespace SampleProject;

class SampleGrid
{
    public function build($grid, $item): void
    {
        $grid->link('showDetail', $item->id);
    }
}
