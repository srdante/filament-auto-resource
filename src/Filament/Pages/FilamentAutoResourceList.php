<?php

namespace Miguilim\FilamentAutoResource\Filament\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class FilamentAutoResourceList extends ListRecords
{
    use Concerns\OverwriteActionInjection;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
