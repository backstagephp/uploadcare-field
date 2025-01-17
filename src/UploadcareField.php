<?php

namespace Vormkracht10\UploadcareField;

use Filament\Facades\Filament;
use Filament\Forms;
use Illuminate\Database\Eloquent\Model;
use Vormkracht10\Backstage\Contracts\FieldContract;
use Vormkracht10\Backstage\Fields\FieldBase;
use Vormkracht10\Backstage\Models\Field;
use Vormkracht10\MediaPicker\Models\Media;
use Vormkracht10\Uploadcare\Enums\Style;
use Vormkracht10\Uploadcare\Forms\Components\Uploadcare as Input;

class UploadcareField extends FieldBase implements FieldContract
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
        $input = self::applyDefaultSettings(Input::make($name)->withMetadata(), $field);

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
        if (! isset($record->values[$field->slug])) {
            return $data;
        }

        $media = Media::whereIn('ulid', $record->values[$field->slug])
            ->get()
            ->map(function ($media) {
                if (! isset($media->metadata['cdnUrl'])) {
                    throw new \Exception('Uploadcare file does not have a CDN URL');
                }

                return $media->metadata['cdnUrl'];
            })->toArray();

        $data['setting'][$field->slug] = json_encode($media);

        return $data;
    }

    public static function mutateBeforeSaveCallback(Model $record, Field $field, array $data): array
    {
        if ($field->field_type !== 'uploadcare') {
            return $data;
        }

        if (! isset($data['setting'][$field->slug])) {
            return $data;
        }

        $values = $data['setting'][$field->slug];

        if (is_string($values)) {
            $values = json_decode($values, true);
        }

        if (! is_array($values)) {
            return $data;
        }

        $media = [];

        foreach ($values as $file) {
            $info = $file['fileInfo'];
            $detailedInfo = ! empty($info['imageInfo'])
                ? $info['imageInfo']
                : (! empty($info['videoInfo'])
                    ? $info['videoInfo']
                    : (! empty($info['contentInfo'])
                        ? $info['contentInfo']
                        : []));

            $media[] = Media::updateOrCreate([
                'site_ulid' => Filament::getTenant()->ulid,
                'disk' => 'uploadcare',
                'original_filename' => $info['name'],
                'checksum' => md5_file($info['cdnUrl']),
            ], [
                'filename' => $info['uuid'],
                'uploaded_by' => auth()->user()->id,
                'extension' => $detailedInfo['format'] ?? null,
                'mime_type' => $info['mimeType'],
                'size' => $info['size'],
                'width' => isset($detailedInfo['width']) ? $detailedInfo['width'] : null,
                'height' => isset($detailedInfo['height']) ? $detailedInfo['height'] : null,
                'public' => config('media-picker.visibility') === 'public',
                'metadata' => $info,
            ]);
        }

        $data['setting'][$field->slug] = collect($media)->map(function ($media) {
            return $media->ulid;
        })->toArray();

        return $data;
    }
}
