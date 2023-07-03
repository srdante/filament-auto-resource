<?php

namespace Miguilim\FilamentAutoResource\Generators;

use Doctrine\DBAL\Types;
use Filament\Facades\Filament;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TableGenerator
{
    use Concerns\CanReadModelSchemas;

    public static array $generatedTableSchemas = [];

    protected Model $dummyModel;

    public function __construct(protected string $modelClass)
    {
        $this->dummyModel = new $modelClass();
    }

    public static function makeTableSchema(string $model, array $visibleColumns, array $enumDictionary = [], array $except = []): array
    {
        $cacheKey = md5($model . json_encode($visibleColumns) . json_encode($enumDictionary) . json_encode($except));
    
        return static::$generatedTableSchemas[$cacheKey] ??= (new self($model))->getResourceTableSchema($visibleColumns, $enumDictionary, $except);
    }

    protected function getResourceTableSchema(array $visibleColumns, array $enumDictionary, array $except): array
    {
        $columns = $this->getResourceTableSchemaColumns($this->modelClass);

        $columnInstances = [];

        foreach ($columns as $key => $value) {
            if (in_array($value['originalName'][0] ?? $key, $except)) {
                continue;
            }

            if (($this->dummyModel->getCasts()[$key] ?? '') === 'json') {
                continue;
            }

            $columnInstance = call_user_func([$value['type'], 'make'], $key);

            if (isset($enumDictionary[$key])) {
                $columnInstance = call_user_func([Tables\Columns\BadgeColumn::class, 'make'], $key);
                $columnInstance->enum(
                    collect($enumDictionary[$key])->mapWithKeys(fn ($value, $key) => [$key => (is_array($value)) ? $value[0] : $value])->all()
                );

                $columnInstance->colors(
                    collect($enumDictionary[$key])
                        ->filter(fn ($value) => is_array($value))
                        ->mapWithKeys(fn ($value, $key) => [$value[1] => $key])
                        ->all()
                );
            }

            if ($this->dummyModel->getKeyName() === $key) {
                $columnInstance->searchable();
            } else {
                $columnInstance->toggleable(
                    isToggledHiddenByDefault: !empty($visibleColumns) && ! in_array($key, $visibleColumns)
                );
            }

            if (isset($value['originalName'])) {
                $this->bindRelatedResourceToRelationship($columnInstance);
            }

            foreach ($value as $valueName => $parameters) {
                if($valueName === 'type' || $valueName === 'originalName') {
                    continue;
                }

                if ($valueName === 'sortable' && array_key_exists('originalName', $value)) {
                    continue; // You cannot sort by a relationship column
                }
                
                $columnInstance->{$valueName}(...$parameters);
            }

            $columnInstances[] = $columnInstance;
        }

        return $columnInstances;
    }

    protected function getResourceTableSchemaColumns(string $model): array
    {
        $table = $this->introspectTable($model);

        $columns = [];

        foreach ($table->getColumns() as $column) {
            $columnName = $column->getName();

            if (Str::of($columnName)->contains([
                'password',
            ])) {
                continue;
            }

            $columnData = [];

            if ($column->getType() instanceof Types\BooleanType) {
                $columnData['type'] = Tables\Columns\IconColumn::class;
                $columnData['boolean'] = [];
                $columnData['sortable'] = [];
            } else {
                $columnData['type'] = Tables\Columns\TextColumn::class;

                if ($column->getType()::class === Types\DateType::class) {
                    $columnData['date'] = [];
                }

                if ($column->getType()::class === Types\DateTimeType::class) {
                    $columnData['dateTime'] = [];
                }

                if ($column->getType()::class === Types\TextType::class) {
                    $columnData['wrap'] = [];
                }

                if (in_array(
                    $column->getType()::class,
                    [
                        Types\DecimalType::class,
                        Types\FloatType::class,
                        Types\BigIntType::class,
                        Types\IntegerType::class,
                        Types\SmallIntType::class,
                        Types\DateType::class,
                        Types\DateTimeType::class
                    ])) {
                    $columnData['sortable'] = [];
                }

                if (Str::of($columnName)->contains(['link', 'url'])) {
                    $columnData['url'] = [fn($record) => $record->{$columnName}];
                    $columnData['openUrlInNewTab'] = [];
                }
            }

            if (Str::of($columnName)->endsWith('_id')) {
                $guessedRelationshipName = $this->guessBelongsToRelationshipName($column, $model);

                if (filled($guessedRelationshipName)) {
                    $guessedRelationshipTitleColumnName = $this->guessBelongsToRelationshipTitleColumnName($column, app($model)->{$guessedRelationshipName}()->getModel()::class);

                    $columnData['originalName'] = [$columnName];
                    $columnName = "{$guessedRelationshipName}.{$guessedRelationshipTitleColumnName}";
                }
            }

            $columns[$columnName] = $columnData;
        }

        return $columns;
    }

    protected function bindRelatedResourceToRelationship(TextColumn $column): TextColumn
    {
        $view = 'view';

        return $column->weight('bold')->url(function ($record) use ($view, $column) {
            if ($record === null) {
                return null;
            }
      
            $selectedResource = null;
            $relationship = Str::before($column->getName(), '.');
            $relatedRecord = $record->{$relationship};
      
            if ($relatedRecord === null) {
                return null;
            }
      
            foreach (Filament::getResources() as $resource) {
                if ($relatedRecord instanceof ($resource::getModel())) {
                    $selectedResource = $resource;
      
                    break;
                }
            }

            if ($selectedResource === null) {
                return null;
            }
      
            return $selectedResource::getUrl($view, $relatedRecord->getKey());
        });
    }
}