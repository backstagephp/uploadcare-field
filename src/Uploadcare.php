<?php

namespace Backstage\UploadcareField;

use Backstage\Fields\Contracts\FieldContract;
use Backstage\Fields\Fields\Base;
use Backstage\Fields\Models\Field;
use Backstage\Media\Models\Media;
use Backstage\Uploadcare\Enums\Style;
use Backstage\Uploadcare\Forms\Components\Uploadcare as Input;
use Filament\Facades\Filament;
use Filament\Forms;
use Illuminate\Database\Eloquent\Model;

class Uploadcare extends Base implements FieldContract
{
    public static function getDefaultConfig(): array
    {
        return [
            ...parent::getDefaultConfig(),
            'uploaderStyle' => Style::INLINE->value,
            'multiple' => false,
            'imagesOnly' => false,
        ];
    }

    public static function make(string $name, Field $field): Input
    {
        $input = self::applyDefaultSettings(
            input: Input::make($name)->withMetadata()
                ->removeCopyright(),
            field: $field
        );

        $input = $input->label($field->name ?? self::getDefaultConfig()['label'] ?? null)
            ->uploaderStyle(Style::tryFrom($field->config['uploaderStyle'] ?? null) ?? Style::tryFrom(self::getDefaultConfig()['uploaderStyle']))
            ->multiple($field->config['multiple'] ?? self::getDefaultConfig()['multiple']);

        if ($field->config['imagesOnly'] ?? self::getDefaultConfig()['imagesOnly']) {
            $input->imagesOnly();
        }

        return $input;
    }

    public function getForm(): array
    {
        return [
            Forms\Components\Tabs::make()
                ->schema([
                    Forms\Components\Tabs\Tab::make('General')
                        ->label(__('General'))
                        ->schema([
                            ...parent::getForm(),
                        ]),
                    Forms\Components\Tabs\Tab::make('Field specific')
                        ->label(__('Field specific'))
                        ->schema([
                            Forms\Components\Grid::make(2)->schema([
                                Forms\Components\Toggle::make('config.multiple')
                                    ->label(__('Multiple'))
                                    ->inline(false),
                                Forms\Components\Toggle::make('config.imagesOnly')
                                    ->label(__('Images only'))
                                    ->inline(false),
                                Forms\Components\Select::make('config.uploaderStyle')
                                    ->label(__('Uploader style'))
                                    ->options([
                                        Style::INLINE->value => __('Inline'),
                                        Style::MINIMAL->value => __('Minimal'),
                                        Style::REGULAR->value => __('Regular'),
                                    ])
                                    ->required(),
                            ]),
                        ]),
                ])->columnSpanFull(),
        ];
    }

    public static function mutateFormDataCallback(Model $record, Field $field, array $data): array
    {
        if (! isset($record->values[$field->ulid])) {
            return $data;
        }

        $media = Media::whereIn('ulid', $record->values[$field->ulid])
            ->get()
            ->map(function ($media) {
                if (! isset($media->metadata['cdnUrl'])) {
                    throw new \Exception('Uploadcare file does not have a CDN URL');
                }

                return $media->metadata['cdnUrl'];
            })->toArray();

        $data[$record->valueColumn][$field->ulid] = json_encode($media);

        return $data;
    }

    public static function mutateBeforeSaveCallback(Model $record, Field $field, array $data): array
    {
        if ($field->field_type !== 'uploadcare') {
            return $data;
        }

        $findInNested = function ($array, $key) use (&$findInNested) {
            foreach ($array as $k => $value) {
                if ($k === $key) {
                    return $value;
                }
                if (is_array($value)) {
                    $result = $findInNested($value, $key);
                    if ($result !== null) {
                        return $result;
                    }
                }
            }

            return null;
        };

        $values = $findInNested($data[$record->valueColumn], $field->ulid);

        if ($values === null) {
            return $data;
        }

        if (is_string($values)) {
            $values = json_decode($values, true);
        }

        if (! is_array($values)) {
            return $data;
        }

        $media = [];

        foreach ($values as $file) {
            if (! is_array($file) && Media::where('checksum', md5_file($file))->exists()) {
                continue;
            }

            $info = $file['fileInfo'];

            $detailedInfo = ! empty($info['imageInfo'])
                ? $info['imageInfo']
                : (! empty($info['videoInfo'])
                    ? $info['videoInfo']
                    : (! empty($info['contentInfo'])
                        ? $info['contentInfo']
                        : []));

            $media[] = Media::updateOrCreate([
                'site_ulid' => Filament::getTenant()?->ulid,
                'disk' => 'uploadcare',
                'original_filename' => $info['name'],
                'checksum' => md5_file($info['cdnUrl']),
            ], [
                'filename' => $info['uuid'],
                'uploaded_by' => auth()->user()?->id,
                'extension' => $detailedInfo['format'] ?? null,
                'mime_type' => $info['mimeType'],
                'size' => $info['size'],
                'width' => isset($detailedInfo['width']) ? $detailedInfo['width'] : null,
                'height' => isset($detailedInfo['height']) ? $detailedInfo['height'] : null,
                'public' => config('media-picker.visibility') === 'public',
                'metadata' => $info,
            ]);
        }

        $data[$record->valueColumn][$field->ulid] = collect($media)->map(function ($media) {
            return $media->ulid;
        })->toArray();

        return $data;
    }
}
