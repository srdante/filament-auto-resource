<?php

namespace Miguilim\FilamentAutoResource;

use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;

class AutoResource extends Resource
{
    public static array $tableColumns = [];

    public static bool $simple = false;

    public static bool $intrusive = true;

    public static function form(Form $form): Form
    {
        return $form
            ->schema(AutoResourceHelper::makeFormSchema(static::$model));
    }

    public static function tableExtra(Table $table): Table
    {
        return $table;
    }

    public static function table(Table $table): Table
    {
        return static::tableExtra(
            $table->columns(AutoResourceHelper::makeTableSchema(static::$model, static::$tableColumns))
        );
    }

    public static function getPages(): array
    {
        if (self::$simple) {
            return [
                'index' => AutoResourceHelper::makeIndex(static::class),
            ];
        }
        
        return [
            'index' => AutoResourceHelper::makeList(static::class),
            'create' => AutoResourceHelper::makeCreate(static::class),
            'edit' => AutoResourceHelper::makeEdit(static::class),
            'view' => AutoResourceHelper::makeView(static::class),
        ];
    }
}
