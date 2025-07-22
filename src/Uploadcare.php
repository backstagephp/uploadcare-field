<?php

namespace Backstage\UploadcareField;

use Backstage\Fields\Contracts\FieldContract;
use Backstage\Fields\Fields\Base;
use Backstage\Fields\Models\Field;
use Backstage\Uploadcare\Enums\Style;
use Backstage\Uploadcare\Forms\Components\Uploadcare as Input;
use Filament\Facades\Filament;
use Filament\Forms;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Uploadcare extends Base implements FieldContract
{
    public static function getDefaultConfig(): array
    {
        return [
            ...parent::getDefaultConfig(),
            'uploaderStyle' => Style::INLINE->value,
            'multiple' => false,
            'imagesOnly' => false,
            'withMetadata' => true,
        ];
    }

    public static function make(string $name, Field $field): Input
    {
        $input = self::applyDefaultSettings(
            input: Input::make($name)->withMetadata()->removeCopyright(),
            field: $field
        );

        $input = $input->label($field->name ?? self::getDefaultConfig()['label'] ?? null)
            ->uploaderStyle(Style::tryFrom($field->config['uploaderStyle'] ?? null) ?? Style::tryFrom(self::getDefaultConfig()['uploaderStyle']))
            ->multiple($field->config['multiple'] ?? self::getDefaultConfig()['multiple'])
            ->withMetadata($field->config['withMetadata'] ?? self::getDefaultConfig()['withMetadata']);

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
                                Forms\Components\Toggle::make('config.withMetadata')
                                    ->label(__('With metadata'))
                                    ->formatStateUsing(function ($state) {
                                        return $state ?? self::getDefaultConfig()['withMetadata'];
                                    })
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
        if (! property_exists($record, 'valueColumn') || ! isset($record->values[$field->ulid])) {
            return $data;
        }

        $values = $record->values[$field->ulid];

        if ($values === '' || $values === [] || $values === null) {
            $data[$record->valueColumn][$field->ulid] = null;

            return $data;
        }

        if (empty($values)) {
            $data[$record->valueColumn][$field->ulid] = [];

            return $data;
        }

        if ($field->config['withMetadata'] ?? self::getDefaultConfig()['withMetadata']) {
            $values = self::parseValues($values);

            $data[$record->valueColumn][$field->ulid] = $values;

            return $data;
        }

        $values = self::parseValues($values);

        if (self::isMediaUlidArray($values)) {
            $mediaUrls = self::extractMediaUrls($values);
        } else {
            $mediaUrls = self::extractCdnUrlsFromFileData($values);
        }

        $data[$record->valueColumn][$field->ulid] = self::filterValidUrls($mediaUrls);

        return $data;
    }

    public static function mutateBeforeSaveCallback(Model $record, Field $field, array $data): array
    {
        if (! property_exists($field, 'field_type') || $field->field_type !== 'uploadcare') {
            return $data;
        }

        if (! property_exists($record, 'valueColumn')) {
            return $data;
        }

        $values = self::findFieldValues($data[$record->valueColumn], (string) $field->ulid);

        if ($values === '' || $values === [] || $values === null) {
            $data[$record->valueColumn][$field->ulid] = null;

            return $data;
        }

        $values = self::normalizeValues($values);

        if (! is_array($values)) {
            return $data;
        }

        $media = self::processUploadedFiles($values);
        $data[$record->valueColumn][$field->ulid] = collect($media)->pluck('ulid')->toArray();

        return $data;
    }

    private static function getMediaModel(): string
    {
        return config('backstage.media.model', 'Backstage\\Models\\Media');
    }

    private static function parseValues(mixed $values): mixed
    {
        if (is_string($values)) {
            $decoded = json_decode($values, true);

            return json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }

        return $values;
    }

    private static function isMediaUlidArray(array $values): bool
    {
        if (! isset($values[0])) {
            return false;
        }

        $firstValue = $values[0];

        if (is_string($firstValue)) {
            return true;
        }

        if (is_array($firstValue) && isset($firstValue['uuid'])) {
            return false;
        }

        return false;
    }

    private static function filterValidUrls(array $urls): array
    {
        return array_filter($urls, function ($url) {
            return filter_var($url, FILTER_VALIDATE_URL) !== false;
        });
    }

    private static function extractMediaUrls(array $mediaUlids): array
    {
        $mediaModel = self::getMediaModel();

        return $mediaModel::whereIn('ulid', $mediaUlids)
            ->get()
            ->map(function ($media) {
                if (! isset($media->metadata['cdnUrl'])) {
                    return null;
                }

                $cdnUrl = $media->metadata['cdnUrl'];

                return filter_var($cdnUrl, FILTER_VALIDATE_URL) ? $cdnUrl : null;
            })
            ->filter()
            ->toArray();
    }

    private static function findFieldValues(array $data, string $fieldUlid): mixed
    {
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

        return $findInNested($data, $fieldUlid);
    }

    private static function normalizeValues(mixed $values): mixed
    {
        if (is_string($values) && json_validate($values)) {
            return json_decode($values, true);
        }

        if (is_string($values)) {
            return $values;
        }

        return $values;
    }

    private static function processUploadedFiles(array $files): array
    {
        $media = [];

        foreach ($files as $file) {
            $normalizedFiles = self::normalizeFileData($file);

            if (self::isArrayOfArrays($normalizedFiles)) {
                foreach ($normalizedFiles as $singleFile) {
                    if (! self::shouldSkipFile($singleFile)) {
                        $media[] = self::createOrUpdateMediaRecord($singleFile);
                    }
                }
            } else {
                if (! self::shouldSkipFile($normalizedFiles)) {
                    $media[] = self::createOrUpdateMediaRecord($normalizedFiles);
                }
            }
        }

        return $media;
    }

    private static function isArrayOfArrays(mixed $data): bool
    {
        return is_array($data) && isset($data[0]) && is_array($data[0]);
    }

    private static function normalizeFileData(mixed $file): mixed
    {
        if (is_string($file)) {
            return json_decode($file, true);
        }

        if (self::isArrayOfArrays($file)) {
            return array_filter($file, 'is_array');
        }

        return $file;
    }

    private static function shouldSkipFile(mixed $file): bool
    {
        if (self::isArrayOfArrays($file)) {
            foreach ($file as $singleFile) {
                if (self::shouldSkipFile($singleFile)) {
                    return true;
                }
            }

            return false;
        }

        if (is_string($file)) {
            return self::mediaExists($file);
        }

        if (is_array($file)) {
            $cdnUrl = self::extractCdnUrl($file);

            return $cdnUrl ? self::mediaExists($cdnUrl) : false;
        }

        return false;
    }

    private static function mediaExists(string $file): bool
    {
        $mediaModel = self::getMediaModel();

        return $mediaModel::where('checksum', md5_file($file))->exists();
    }

    private static function extractCdnUrl(array $file): ?string
    {
        $url = $file['cdnUrl'] ?? $file['fileInfo']['cdnUrl'] ?? null;

        return $url && filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    private static function createOrUpdateMediaRecord(array $file): Model
    {
        $mediaModel = self::getMediaModel();

        if (self::isArrayOfArrays($file)) {
            $file = $file[0];
        }

        $info = $file['fileInfo'] ?? $file;
        $detailedInfo = self::extractDetailedInfo($info);

        $tenantUlid = null;
        if (class_exists(Filament::class) && method_exists(Filament::class, 'getTenant')) {
            $tenant = Filament::getTenant();
            $tenantUlid = $tenant?->ulid ?? null;
        }

        return $mediaModel::updateOrCreate([
            'site_ulid' => $tenantUlid,
            'disk' => 'uploadcare',
            'original_filename' => $info['name'],
            'checksum' => md5_file($info['cdnUrl']),
        ], [
            'filename' => $info['uuid'],
            'uploaded_by' => Auth::user()?->id,
            'extension' => $detailedInfo['format'] ?? null,
            'mime_type' => $info['mimeType'],
            'size' => $info['size'],
            'width' => $detailedInfo['width'] ?? null,
            'height' => $detailedInfo['height'] ?? null,
            'public' => config('media-picker.visibility') === 'public',
            'metadata' => json_encode($info),
        ]);
    }

    private static function extractDetailedInfo(array $info): array
    {
        return $info['imageInfo'] ?? $info['videoInfo'] ?? $info['contentInfo'] ?? [];
    }

    private static function extractCdnUrlsFromFileData(array $files): array
    {
        $cdnUrls = [];

        if (! is_array($files)) {
            return $cdnUrls;
        }

        foreach ($files as $file) {
            if (! is_array($file)) {
                continue;
            }

            $cdnUrl = self::extractCdnUrl($file);
            if ($cdnUrl) {
                $cdnUrls[] = $cdnUrl;
            }
        }

        return $cdnUrls;
    }
}
