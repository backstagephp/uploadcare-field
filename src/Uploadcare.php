<?php

namespace Backstage\UploadcareField;

use Backstage\Fields\Contracts\FieldContract;
use Backstage\Fields\Contracts\HydratesValues;
use Backstage\Fields\Fields\Base;
use Backstage\Fields\Models\Field;
use Backstage\Uploadcare\Enums\Style;
use Backstage\Uploadcare\Forms\Components\Uploadcare as Input;
use Backstage\UploadcareField\Forms\Components\MediaGridPicker;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Uploadcare extends Base implements FieldContract, HydratesValues
{
    public static function getDefaultConfig(): array
    {
        return [
            ...parent::getDefaultConfig(),
            'uploaderStyle' => Style::INLINE->value,
            'multiple' => false,
            'imagesOnly' => false,
            'withMetadata' => true,
            'cropPreset' => '',
            'acceptedFileTypes' => null,
        ];
    }

    public static function make(string $name, Field $field): Input
    {
        $input = self::applyDefaultSettings(
            input: Input::make($name)->withMetadata()->removeCopyright(),
            field: $field
        );

        $isMultiple = $field->config['multiple'] ?? self::getDefaultConfig()['multiple'];
        $acceptedFileTypes = self::parseAcceptedFileTypes($field);

        // TODO: Implement media picker when we got it working fully. Remember to check content_field_values and media_relations as well.
        $input = $input->hintActions([
            Action::make('mediaPicker')
                ->hiddenLabel()
                ->tooltip(__('Select from Media'))
                ->icon(Heroicon::Photo)
                ->color('gray')
                ->size('sm')
                ->modalHeading(__('Select Media'))
                ->modalWidth('Screen')
                ->modalCancelActionLabel(__('Cancel'))
                ->modalSubmitActionLabel(__('Select'))
                ->action(function (Action $action, array $data, $livewire) use ($input) {
                    $selectedMediaUuid = $data['selected_media_uuid'] ?? null;

                    if ($selectedMediaUuid) {
                        $cdnUrls = self::convertUuidsToCdnUrls($selectedMediaUuid);

                        if ($cdnUrls) {
                            self::updateStateWithSelectedMedia($input, $cdnUrls);
                        }
                    }
                })
                ->schema([
                    MediaGridPicker::make('media_picker')
                        ->label('')
                        ->hiddenLabel()
                        ->fieldName($name)
                        ->perPage(12)
                        ->multiple($isMultiple)
                        ->acceptedFileTypes($acceptedFileTypes),
                    \Filament\Forms\Components\Hidden::make('selected_media_uuid')
                        ->default(null)
                        ->dehydrated()
                        ->live(),
                ]),
        ]);

        $input = $input->label($field->name ?? self::getDefaultConfig()['label'] ?? null)
            ->uploaderStyle(Style::tryFrom($field->config['uploaderStyle'] ?? null) ?? Style::tryFrom(self::getDefaultConfig()['uploaderStyle']))
            ->multiple($field->config['multiple'] ?? self::getDefaultConfig()['multiple'])
            ->withMetadata($field->config['withMetadata'] ?? self::getDefaultConfig()['withMetadata'])
            ->cropPreset($field->config['cropPreset'] ?? self::getDefaultConfig()['cropPreset']);

        if ($acceptedFileTypes) {
            $input->acceptedFileTypes($acceptedFileTypes);
        }

        if ($field->config['imagesOnly'] ?? self::getDefaultConfig()['imagesOnly']) {
            $input->imagesOnly();
        }

        return $input;
    }

    private static function parseAcceptedFileTypes(Field $field): ?array
    {
        if (! isset($field->config['acceptedFileTypes']) || ! $field->config['acceptedFileTypes']) {
            return null;
        }

        $types = $field->config['acceptedFileTypes'];

        if (is_array($types)) {
            return $types;
        }

        $types = explode(',', $types);

        return array_map('trim', $types);
    }

    public function getForm(): array
    {
        return [
            Tabs::make()
                ->schema([
                    Tab::make('General')
                        ->label(__('General'))
                        ->schema([
                            ...parent::getForm(),
                        ]),
                    Tab::make('Field specific')
                        ->label(__('Field specific'))
                        ->schema([
                            Grid::make(2)->schema([
                                Toggle::make('config.multiple')
                                    ->label(__('Multiple'))
                                    ->inline(false),
                                Toggle::make('config.withMetadata')
                                    ->label(__('With metadata'))
                                    ->formatStateUsing(function ($state, $record) {
                                        // Check if withMetadata exists in the config
                                        if ($record === null) {
                                            return self::getDefaultConfig()['withMetadata'];
                                        }

                                        $config = is_string($record->config) ? json_decode($record->config, true) : $record->config;

                                        return isset($config['withMetadata']) ? $config['withMetadata'] : self::getDefaultConfig()['withMetadata'];
                                    })
                                    ->inline(false),
                                Toggle::make('config.imagesOnly')
                                    ->label(__('Images only'))
                                    ->inline(false),
                                Select::make('config.uploaderStyle')
                                    ->label(__('Uploader style'))
                                    ->options([
                                        Style::INLINE->value => __('Inline'),
                                        Style::MINIMAL->value => __('Minimal'),
                                        Style::REGULAR->value => __('Regular'),
                                    ])
                                    ->required(),
                                TextInput::make('config.cropPreset')
                                    ->label(__('Crop preset'))
                                    ->placeholder(__('e.g., "free, 1:1, 16:9" or leave empty to disable'))
                                    ->helperText(__('Comma-separated aspect ratios (e.g., "free, 1:1, 16:9, 4:3") or empty to disable cropping'))
                                    ->columnSpanFull(),
                                Select::make('config.acceptedFileTypes')
                                    ->label(__('Accepted file types'))
                                    ->options(function ($state) {
                                        $options = [
                                            'image/*' => __('Image'),
                                            'video/*' => __('Video'),
                                            'audio/*' => __('Audio'),
                                            'application/*' => __('Application (Word, Excel, PowerPoint, etc.)'),
                                            'application/pdf' => __('PDF'),
                                            'application/zip' => __('ZIP'),
                                        ];

                                        if ($state) {
                                            foreach ($state as $type) {
                                                if (! array_key_exists($type, $options)) {
                                                    $options[$type] = $type;
                                                }
                                            }
                                        }

                                        return $options;
                                    })
                                    ->createOptionForm([
                                        TextInput::make('mime_type')
                                            ->label('Mime type or extension (e.g. .jpg)')
                                            ->required(),
                                    ])
                                    ->createOptionUsing(function (array $data) {
                                        return $data['mime_type'];
                                    })
                                    ->multiple()
                                    ->columnSpanFull(),
                            ]),
                        ]),
                ])->columnSpanFull(),
        ];
    }

    public static function mutateFormDataCallback(Model $record, Field $field, array $data): array
    {
        if (($field->field_type ?? '') !== 'uploadcare') {
            return $data;
        }

        if (! property_exists($record, 'valueColumn') || ! isset($record->values[$field->ulid])) {
            return $data;
        }

        $values = $record->values[$field->ulid];

        if ($values == '' || $values == [] || $values == null || empty($values)) {
            $data[$record->valueColumn][$field->ulid] = [];

            return $data;
        }

        $withMetadata = $field->config['withMetadata'] ?? self::getDefaultConfig()['withMetadata'];
        $values = self::parseValues($values);

        if (self::isMediaUlidArray($values)) {
            $mediaData = self::extractMediaUrls($values, $withMetadata);
            $data[$record->valueColumn][$field->ulid] = $mediaData;
        } else {
            $mediaUrls = self::extractCdnUrlsFromFileData($values);
            $data[$record->valueColumn][$field->ulid] = $withMetadata ? $values : self::filterValidUrls($mediaUrls);
        }

        return $data;
    }

    public static function mutateBeforeSaveCallback(Model $record, Field $field, array $data): array
    {
        if (($field->field_type ?? '') !== 'uploadcare') {
            return $data;
        }

        if (! property_exists($record, 'valueColumn')) {
            return $data;
        }

        $values = self::findFieldValues($data[$record->valueColumn] ?? [], (string) $field->ulid);

        if ($values === '' || $values === [] || $values === null) {
            $data[$record->valueColumn][$field->ulid] = null;

            return $data;
        }

        $values = self::normalizeValues($values);

        if (! is_array($values)) {
            return $data;
        }

        $media = self::processUploadedFiles($values);

        // We save the full values including metadata so they can be processed by the Observer
        // into relationships. The Observer will then clear the value column.
        $data[$record->valueColumn][$field->ulid] = $values;

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

    private static function isMediaUlidArray(mixed $values): bool
    {
        if (! is_array($values)) {
            return false;
        }

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

    private static function extractMediaUrls(array $mediaUlids, bool $withMetadata = false): array
    {
        $mediaModel = self::getMediaModel();

        return $mediaModel::whereIn('ulid', $mediaUlids)
            ->get()
            ->map(function ($media) use ($withMetadata) {
                $metadata = is_string($media->metadata)
                    ? json_decode($media->metadata, true)
                    : $media->metadata;

                if (! isset($metadata['cdnUrl'])) {
                    return null;
                }

                if ($withMetadata) {
                    return $metadata;
                }

                $cdnUrl = $metadata['cdnUrl'];

                return filter_var($cdnUrl, FILTER_VALIDATE_URL) ? $cdnUrl : null;
            })
            ->filter()
            ->values()
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

        foreach ($files as $index => $file) {
            $normalizedFiles = self::normalizeFileData($file);

            if ($normalizedFiles === null || $normalizedFiles === false) {
                continue;
            }

            if (self::isArrayOfArrays($normalizedFiles)) {
                foreach ($normalizedFiles as $singleFile) {
                    if ($singleFile !== null && ! self::shouldSkipFile($singleFile)) {
                        $media[] = self::createOrUpdateMediaRecord($singleFile);
                    }
                }
            } else {
                if (is_array($normalizedFiles) && ! self::shouldSkipFile($normalizedFiles)) {
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
        if ($file === null || (! is_array($file) && ! is_string($file))) {
            return true;
        }

        if (self::isArrayOfArrays($file)) {
            foreach ($file as $index => $singleFile) {
                if (self::shouldSkipFile($singleFile)) {
                    return true;
                }
            }

            return false;
        }

        if (is_string($file)) {
            $uuid = self::extractUuidFromString($file);

            return $uuid ? self::mediaExistsByUuid($uuid) : false;
        }

        if (is_array($file)) {
            $uuid = $file['uuid'] ?? $file['fileInfo']['uuid'] ?? null;

            return $uuid ? self::mediaExistsByUuid($uuid) : false;
        }

        return false;
    }

    private static function mediaExistsByUuid(string $uuid): bool
    {
        $mediaModel = self::getMediaModel();

        return $mediaModel::where('filename', $uuid)->exists();
    }

    private static function extractUuidFromString(string $string): ?string
    {
        if (preg_match('/~\d+\//', $string)) {
            return null;
        }

        if (preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $string)) {
            return $string;
        }

        if (preg_match('/\/([a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12})(?:\/|$)/i', $string, $matches)) {
            return $matches[1];
        }

        return null;
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

        $tenantUlid = Filament::getTenant()->ulid ?? null;

        $media = $mediaModel::updateOrCreate([
            'site_ulid' => $tenantUlid,
            'disk' => 'uploadcare',
            'filename' => $info['uuid'],
        ], [
            'original_filename' => $info['name'],
            'uploaded_by' => Auth::id(),
            'extension' => $detailedInfo['format'] ?? null,
            'mime_type' => $info['mimeType'],
            'size' => $info['size'],
            'width' => $detailedInfo['width'] ?? null,
            'height' => $detailedInfo['height'] ?? null,
            'alt' => null,
            'public' => config('backstage.media.visibility') === 'public',
            'metadata' => $info,
            'checksum' => md5($info['uuid']),
        ]);

        return $media;
    }

    private static function extractDetailedInfo(array $info): array
    {
        return $info['imageInfo'] ?? $info['videoInfo'] ?? $info['contentInfo'] ?? [];
    }

    private static function extractCdnUrlsFromFileData(mixed $files): array
    {
        if (! is_array($files)) {
            return [];
        }

        $cdnUrls = [];

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

    private static function convertUuidsToCdnUrls(mixed $uuids): mixed
    {
        if (empty($uuids)) {
            return null;
        }

        if (is_string($uuids)) {
            $decoded = json_decode($uuids, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $uuids = $decoded;
            } elseif (self::isValidCdnUrl($uuids)) {
                return $uuids;
            }
        }

        if (is_array($uuids)) {
            $urls = array_map(fn ($uuid) => self::resolveCdnUrl($uuid), $uuids);

            return array_filter($urls);
        }

        return self::resolveCdnUrl($uuids);
    }

    private static function resolveCdnUrl(mixed $uuid): ?string
    {
        if (! is_string($uuid) || empty($uuid)) {
            return null;
        }

        if (filter_var($uuid, FILTER_VALIDATE_URL)) {
            return $uuid;
        }

        if (str_contains($uuid, 'ucarecdn.com')) {
            return $uuid;
        }

        $mediaModel = self::getMediaModel();

        $media = $mediaModel::where('filename', $uuid)
            ->orWhere('metadata->cdnUrl', 'like', '%' . $uuid . '%')
            ->first();

        if ($media && isset($media->metadata['cdnUrl'])) {
            return $media->metadata['cdnUrl'];
        }

        return 'https://ucarecdn.com/' . $uuid . '/';
    }

    private static function isValidCdnUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) && str_contains($url, 'ucarecdn.com');
    }

    private static function updateStateWithSelectedMedia(Input $input, mixed $urls): void
    {
        if (! $urls) {
            return;
        }

        if (! $input->isMultiple()) {
            $input->state($urls);
            $input->callAfterStateUpdated();

            return;
        }

        $currentState = self::normalizeCurrentState($input->getState());

        if (is_string($urls)) {
            $urls = [$urls];
        }

        $newState = array_unique(array_merge($currentState, $urls), SORT_REGULAR);

        $input->state($newState);
        $input->callAfterStateUpdated();
    }

    private static function normalizeCurrentState(mixed $state): array
    {
        if (is_string($state)) {
            $state = json_decode($state, true) ?? [];
        }

        if (! is_array($state)) {
            return [];
        }

        // Handle double-encoded JSON or nested structures
        if (count($state) > 0 && is_string($state[0])) {
            $firstItem = json_decode($state[0], true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($firstItem)) {
                if (count($state) === 1 && array_is_list($firstItem)) {
                    return $firstItem;
                }

                return array_map(function ($item) {
                    if (is_string($item)) {
                        $decoded = json_decode($item, true);

                        return $decoded ?: $item;
                    }

                    return $item;
                }, $state);
            }
        }

        return $state;
    }

    public function hydrate(mixed $value, ?Model $model = null): mixed
    {
        // Try to load from model relationship if available
        $hydratedFromModel = self::hydrateFromModel($model);

        if ($hydratedFromModel !== null) {
            return $hydratedFromModel;
        }

        if (empty($value)) {
            return $value;
        }

        $mediaModel = self::getMediaModel();

        if (is_string($value) && ! json_validate($value)) {
            return $mediaModel::where('ulid', $value)->first() ?? $value;
        }

        $hydratedUlids = self::hydrateBackstageUlids($value);
        if ($hydratedUlids !== null) {
            return $hydratedUlids;
        }

        return $value;
    }

    private static function hydrateFromModel(?Model $model): ?array
    {
        if (! $model || ! method_exists($model, 'media')) {
            return null;
        }

        if (! $model->relationLoaded('media')) {
            $model->load('media');
        }

        if ($model->media->isEmpty()) {
            return null;
        }

        return $model->media->map(function ($media) {
            $meta = $media->pivot->meta ? json_decode($media->pivot->meta, true) : [];

            return array_merge($media->toArray(), $meta, [
                'uuid' => $media->filename,
                'cdnUrl' => $meta['cdnUrl'] ?? $media->metadata['cdnUrl'] ?? null,
            ]);
        })->toArray();
    }

    private static function hydrateBackstageUlids(mixed $value): ?array
    {
        $isListOfUlids = is_array($value) && ! empty($value) && is_string($value[0]) && ! json_validate($value[0]);

        if (! $isListOfUlids) {
            return null;
        }

        $mediaModel = self::getMediaModel();
        $mediaItems = $mediaModel::whereIn('ulid', $value)->get();
        $hydrated = [];

        foreach ($value as $ulid) {
            $media = $mediaItems->firstWhere('ulid', $ulid);
            if ($media) {
                $hydrated[] = $media->load('edits');
            }
        }

        return ! empty($hydrated) ? $hydrated : null;
    }
}
