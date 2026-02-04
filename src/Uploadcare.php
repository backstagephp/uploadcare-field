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
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class Uploadcare extends Base implements FieldContract, HydratesValues
{
    public function getFieldType(): ?string
    {
        return 'uploadcare';
    }

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
            input: Input::make($name)
                ->withMetadata()
                ->removeCopyright()
                ->dehydrateStateUsing(function ($state, $component, $record) {

                    if (is_string($state) && json_validate($state)) {
                        $state = json_decode($state, true);
                    }

                    // Ensure Media models are properly mapped to include crop data
                    if ($state instanceof \Illuminate\Database\Eloquent\Collection) {
                        return $state->map(fn ($item) => $item instanceof Model ? self::mapMediaToValue($item) : $item)->all();
                    }

                    if (is_array($state) && array_is_list($state)) {
                        $result = array_map(function ($item) {
                            if ($item instanceof Model || is_array($item)) {
                                return self::mapMediaToValue($item);
                            }

                            return $item;
                        }, $state);

                        /*
                        // Ensure we return a single object (or string) for non-multiple fields during dehydration
                        // to prevent Filament from clearing the state.
                        if (! $component->isMultiple() && ! empty($result)) {
                            return $result[0];
                        }
                        */

                        return $result;
                    }

                    if (is_array($state)) {
                        return self::mapMediaToValue($state);
                    }

                    return $state;
                })
                ->afterStateHydrated(function ($component, $state) {
                    $fieldName = $component->getName();
                    $record = $component->getRecord();

                    $newState = $state;

                    if ($state instanceof \Illuminate\Database\Eloquent\Collection) {
                        $newState = $state->map(fn ($item) => $item instanceof Model ? self::mapMediaToValue($item) : $item)->all();
                    } elseif (is_array($state) && ! empty($state)) {
                        $isList = array_is_list($state);
                        $firstKey = array_key_first($state);
                        $firstItem = $state[$firstKey];

                        if ($isList && ($firstItem instanceof Model || is_array($firstItem))) {
                            $newState = array_map(fn ($item) => self::mapMediaToValue($item), $state);
                        } elseif (! $isList && (isset($state['uuid']) || isset($state['cdnUrl']))) {
                            // Single rich object
                            $newState = [self::mapMediaToValue($state)];
                        } elseif ($isList && is_string($firstItem) && preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $firstItem)) {
                            // Resolution of ULIDs handled below
                            $newState = $state;
                        } elseif (is_array($firstItem)) {
                            // Possibly a list of something else or nested
                            $newState = array_map(fn ($item) => self::mapMediaToValue($item), $state);
                        }
                    } elseif ($state instanceof Model) {
                        $newState = [self::mapMediaToValue($state)];
                    }

                    // Resolve ULIDs if we have a list of strings
                    if (is_array($newState) && array_is_list($newState) && count($newState) > 0 && is_string($newState[0]) && preg_match('/^[0-9A-Z]{26}$/i', $newState[0])) {
                        // Resolve ULIDs
                        $potentialUlids = collect($newState)->filter(fn ($s) => is_string($s) && preg_match('/^[0-9A-Z]{26}$/i', $s));
                        $mediaModel = self::getMediaModel();
                        $foundModels = new \Illuminate\Database\Eloquent\Collection;

                        if ($record && $fieldName && $potentialUlids->isNotEmpty()) {
                            try {
                                // Robust field ULID resolution (matching component logic)
                                $fieldUlid = $fieldName;
                                if (str_contains($fieldName, '.')) {
                                    $parts = explode('.', $fieldName);
                                    foreach ($parts as $part) {
                                        if (preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $part)) {
                                            $fieldUlid = $part;

                                            break;
                                        }
                                    }
                                }

                                $fieldValue = \Backstage\Models\ContentFieldValue::where('content_ulid', $record->getKey())
                                    ->where(function ($query) use ($fieldUlid) {
                                        $query->where('field_ulid', $fieldUlid)
                                            ->orWhere('ulid', $fieldUlid);
                                    })
                                    ->first();

                                if ($fieldValue) {
                                    $foundModels = $fieldValue->media()
                                        ->whereIn('media_ulid', $potentialUlids->toArray())
                                        ->get();
                                }
                            } catch (\Exception $e) {
                            }
                        }

                        if ($foundModels->isEmpty() && $potentialUlids->isNotEmpty()) {
                            $foundModels = $mediaModel::whereIn('ulid', $potentialUlids->toArray())->get();
                        }

                        if ($foundModels->isNotEmpty()) {
                            if ($record) {
                                $foundModels->each(function ($m) use ($record) {
                                    $mediaUlid = $m->ulid ?? 'UNKNOWN';

                                    if ($m->relationLoaded('pivot') && $m->pivot && $m->pivot->meta) {
                                        $meta = is_string($m->pivot->meta) ? json_decode($m->pivot->meta, true) : $m->pivot->meta;
                                        if (is_array($meta)) {
                                            $m->setAttribute('hydrated_edit', $meta);
                                        }
                                    }
                                    $contextModel = clone $record;
                                    if ($m->relationLoaded('pivot') && $m->pivot) {
                                        $contextModel->setRelation('pivot', $m->pivot);
                                    } else {
                                        $dummyPivot = new \Backstage\Models\ContentFieldValue;
                                        $dummyPivot->setAttribute('meta', null);
                                        $contextModel->setRelation('pivot', $dummyPivot);
                                    }
                                    $m->setRelation('edits', new \Illuminate\Database\Eloquent\Collection([$contextModel]));
                                });
                            }

                            if ($foundModels->count() === 1 && count($state) > 1) {
                                $newState = [self::mapMediaToValue($foundModels->first())];
                            } else {
                                $newState = $foundModels->map(fn ($m) => self::mapMediaToValue($m))->all();
                            }

                        } else {
                            // Process each item in the state array
                            $extractedFiles = [];

                            foreach ($state as $item) {
                                if (is_array($item)) {
                                    $extractedFiles[] = self::mapMediaToValue($item);

                                    continue;
                                }

                                if (! is_string($item)) {
                                    continue;
                                }

                                $uuid = null;
                                $cdnUrl = null;
                                $filename = null;

                                // Check if it's a UUID
                                if (preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $item)) {
                                    $uuid = $item;
                                }
                                // Check if it's a CDN URL
                                elseif (str_contains($item, 'ucarecd.net/') && filter_var($item, FILTER_VALIDATE_URL)) {
                                    $cdnUrl = $item;
                                    $uuid = self::extractUuidFromString($cdnUrl);
                                }
                                // Check if it's a filename
                                elseif (preg_match('/\.[a-z0-9]{3,4}$/i', $item) && ! str_starts_with($item, 'http')) {
                                    $filename = $item;
                                }

                                // If we found a UUID or CDN URL, add it to the extracted files
                                if ($uuid || $cdnUrl) {
                                    $fileData = [
                                        'uuid' => $uuid ?? self::extractUuidFromString($cdnUrl ?? ''),
                                        'cdnUrl' => $cdnUrl ?? ($uuid ? 'https://ucarecdn.com/'.$uuid.'/' : null),
                                        'original_filename' => $filename,
                                        'name' => $filename,
                                    ];
                                    $extractedFiles[] = self::mapMediaToValue($fileData);
                                }
                            }

                            if (! empty($extractedFiles)) {
                                $newState = $extractedFiles;

                            } else {
                                if (array_is_list($state)) {
                                    $newState = array_map(function ($item) {
                                        if (is_string($item) && json_validate($item)) {
                                            return self::mapMediaToValue(json_decode($item, true));
                                        }

                                        return self::mapMediaToValue($item);
                                    }, $state);
                                } else {
                                    $newState = self::mapMediaToValue($state);
                                }
                            }
                        }

                    } elseif (is_string($state) && json_validate($state)) {

                        $newState = json_decode($state, true);
                    } else {

                    }

                    if ($newState !== $state) {
                        $component->state($newState);
                    }
                }),
            field: $field
        );

        $isMultiple = $field->config['multiple'] ?? self::getDefaultConfig()['multiple'];
        $acceptedFileTypes = self::parseAcceptedFileTypes($field);

        $input = $input->hintActions([
            fn (Input $component) => Action::make('mediaPicker')
                ->schemaComponent($component)
                ->hiddenLabel()
                ->tooltip('Select from Media')
                ->icon(Heroicon::Photo)
                ->color('gray')
                ->size('sm')
                ->modalHeading('Select Media')
                ->modalWidth('Screen')
                ->modalCancelActionLabel('Cancel')
                ->modalSubmitActionLabel('Select')
                ->action(function (Action $action, array $data, Input $component) {
                    $selected = $data['selected_media_uuid'] ?? null;
                    if (! $selected) {
                        return;
                    }

                    $cdnUrls = self::convertUuidsToCdnUrls($selected);
                    if (! $cdnUrls) {
                        return;
                    }

                    self::updateStateWithSelectedMedia($component, $cdnUrls);
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

        $values = null;

        // 1. Try to get from property first (set by EditContent)
        if (isset($record->values) && is_array($record->values)) {
            $values = $record->values[$field->ulid] ?? null;

        }

        // 2. Fallback to getFieldValueFromRecord which checks relationships
        if ($values === null) {
            $values = self::getFieldValueFromRecord($record, $field);

        }

        if ($values === '' || $values === [] || $values === null || empty($values)) {
            $data[$record->valueColumn ?? 'values'][$field->ulid] = [];

            return $data;
        }

        $withMetadata = $field->config['withMetadata'] ?? self::getDefaultConfig()['withMetadata'];
        $values = self::parseValues($values);

        if (self::isMediaUlidArray($values)) {
            $mediaData = null;

            if ($record->exists && class_exists(\Backstage\Models\ContentFieldValue::class)) {
                try {
                    $cfv = \Backstage\Models\ContentFieldValue::where('content_ulid', $record->ulid)
                        ->where('field_ulid', $field->ulid)
                        ->first();

                    if ($cfv) {
                        $models = self::hydrateFromModel($cfv, $values, true);
                        if ($models && $models instanceof \Illuminate\Support\Collection) {
                            $mediaData = $models->map(fn ($m) => self::mapMediaToValue($m))->values()->all();
                        }
                    }
                } catch (\Exception $e) {
                    // Fallback to simple extraction
                }
            }

            if (empty($mediaData)) {
                $mediaData = self::extractMediaUrls($values, true);
            }

            $data[$record->valueColumn ?? 'values'][$field->ulid] = $mediaData;
        } else {
            $mediaUrls = self::extractCdnUrlsFromFileData($values);
            $result = $withMetadata ? $values : self::filterValidUrls($mediaUrls);
            $data[$record->valueColumn ?? 'values'][$field->ulid] = $result;
        }

        return $data;
    }

    public static function mutateBeforeSaveCallback(Model $record, Field $field, array $data): array
    {
        if (($field->field_type ?? '') !== 'uploadcare') {
            return $data;
        }

        // Handle valueColumn default or missing property
        $valueColumn = $record->valueColumn ?? 'values';

        $values = self::findFieldValues($data, $field);

        if ($values === '' || $values === [] || $values === null || empty($values)) {
            // Check if key exists using strict check to avoid wiping out data that wasn't submitted
            $fieldFound = array_key_exists($field->ulid, $data) ||
                         array_key_exists($field->slug, $data) ||
                         (isset($data['values']) && is_array($data['values']) && (array_key_exists($field->ulid, $data['values']) || array_key_exists($field->slug, $data['values'])));

            if ($fieldFound) {
                $data[$valueColumn][$field->ulid] = [];
            }

            return $data;
        }

        $values = self::normalizeValues($values);

        // Side effect: create media records for new uploads
        self::processUploadedFiles($values);

        // Save the values (Array) - Filament/PersistsContentData will handle encoding if needed
        $data[$valueColumn][$field->ulid] = $values;

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

        return $mediaModel::whereIn('ulid', array_filter(Arr::flatten($mediaUlids), 'is_string'))
            ->get()
            ->map(function ($media) use ($withMetadata) {
                $metadata = is_string($media->metadata)
                    ? json_decode($media->metadata, true)
                    : $media->metadata;

                $metadata = is_array($metadata) ? $metadata : [];

                // Prefer per-edit pivot meta when available (e.g. cropped/modified cdnUrl).
                // In Backstage this is exposed as $media->edit (see Backstage\Models\Media).
                $editMeta = $media->edit ?? null;
                if (is_string($editMeta)) {
                    $editMeta = json_decode($editMeta, true);
                }
                if (is_array($editMeta)) {
                    $metadata = array_merge($metadata, $editMeta);
                }

                $cdnUrl = $metadata['cdnUrl']
                    ?? ($metadata['fileInfo']['cdnUrl'] ?? null);

                $uuid = $metadata['uuid']
                    ?? ($metadata['fileInfo']['uuid'] ?? null)
                    ?? (is_string($media->filename) ? self::extractUuidFromString($media->filename) : null);

                // Fallback for older records: construct a default Uploadcare URL if we only have a UUID.
                if (! $cdnUrl && $uuid) {
                    $cdnUrl = 'https://ucarecdn.com/'.$uuid.'/';
                }

                if (! $cdnUrl || ! filter_var($cdnUrl, FILTER_VALIDATE_URL)) {
                    return null;
                }

                if ($withMetadata) {
                    $result = array_merge($metadata, array_filter([
                        'uuid' => $uuid,
                        'cdnUrl' => $cdnUrl,
                    ]));

                    return $result;
                }

                return $cdnUrl;
            })
            ->filter()
            ->values()
            ->toArray();
    }

    private static function findFieldValues(array $data, Field $field): mixed
    {
        $fieldUlid = (string) $field->ulid;
        $fieldSlug = (string) $field->slug;

        // Try direct key first (most common)
        if (array_key_exists($fieldUlid, $data)) {
            return $data[$fieldUlid];
        }
        if (array_key_exists($fieldSlug, $data)) {
            return $data[$fieldSlug];
        }

        // Recursive search that correctly traverses lists (repeaters/builders)
        $notFound = new \stdClass;
        $findInNested = function ($array, $ulid, $slug, $depth = 0) use (&$findInNested, $notFound) {

            // First pass: look for direct keys at this level
            if (array_key_exists($ulid, $array)) {

                return $array[$ulid];
            }
            if (array_key_exists($slug, $array)) {
                return $array[$slug];
            }

            // Second pass: recurse
            foreach ($array as $k => $value) {
                if (is_array($value)) {
                    $result = $findInNested($value, $ulid, $slug, $depth + 1);
                    if ($result !== $notFound) {
                        return $result;
                    }
                }
            }

            return $notFound;
        };

        $result = $findInNested($data, $fieldUlid, $fieldSlug);

        if ($result === $notFound) {
            $result = null;
            $found = false;
        } else {
            $found = true;
        }

        return $result;
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

    private static function processUploadedFiles(mixed $files): array
    {
        if (! empty($files) && ! array_is_list($files)) {
            $files = [$files];
        }

        $media = [];

        foreach ($files as $index => $file) {
            $normalizedFiles = self::normalizeFileData($file);

            if ($normalizedFiles === null || $normalizedFiles === false) {
                continue;
            }

            if (self::isArrayOfArrays($normalizedFiles)) {
                foreach ($normalizedFiles as $singleFile) {
                    if ($singleFile !== null) {
                        $media[] = self::createOrUpdateMediaRecord($singleFile);
                    }
                }
            } else {
                if (is_array($normalizedFiles)) {
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

    private static function mediaExistsByUuid(string $uuid): bool
    {
        $mediaModel = self::getMediaModel();

        return $mediaModel::where('filename', $uuid)->exists();
    }

    private static function extractUuidFromString(string $string): ?string
    {
        if (preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $string)) {
            return $string;
        }

        if (preg_match('/([a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12})/i', $string, $matches)) {
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
            'filename' => $info['uuid'] ?? ($info['fileInfo']['uuid'] ?? null),
        ], [
            'original_filename' => $info['name'] ?? ($info['original_filename'] ?? 'unknown'),
            'uploaded_by' => Auth::id(),
            'extension' => $detailedInfo['format'] ?? null,
            'mime_type' => $info['mimeType'] ?? ($info['mime_type'] ?? null),
            'size' => $info['size'] ?? 0,
            'width' => $detailedInfo['width'] ?? null,
            'height' => $detailedInfo['height'] ?? null,
            'alt' => null,
            'public' => config('backstage.media.visibility') === 'public',
            'metadata' => $info,
            'checksum' => md5($info['uuid'] ?? uniqid()),
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

        // If this is a Media ULID, resolve to stored CDN URL (or derive from filename UUID).
        if (preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $uuid)) {
            $mediaModel = self::getMediaModel();
            $media = $mediaModel::where('ulid', $uuid)->first();
            if (! $media) {
                return null;
            }

            $metadata = is_string($media->metadata) ? json_decode($media->metadata, true) : $media->metadata;
            $metadata = is_array($metadata) ? $metadata : [];

            // Prefer edit/pivot meta if exposed
            $editMeta = $media->edit ?? null;
            if (is_string($editMeta)) {
                $editMeta = json_decode($editMeta, true);
            }
            if (is_array($editMeta)) {
                $metadata = array_merge($metadata, $editMeta);
            }

            $cdnUrl = $metadata['cdnUrl'] ?? ($metadata['fileInfo']['cdnUrl'] ?? null);
            $fileUuid = $metadata['uuid'] ?? ($metadata['fileInfo']['uuid'] ?? null) ?? self::extractUuidFromString((string) ($media->filename ?? ''));

            if (! $cdnUrl && $fileUuid) {
                $cdnUrl = 'https://ucarecdn.com/'.$fileUuid.'/';
            }

            return is_string($cdnUrl) && filter_var($cdnUrl, FILTER_VALIDATE_URL) ? $cdnUrl : null;
        }

        if (str_contains($uuid, 'ucarecdn.com') || str_contains($uuid, 'ucarecd.net')) {
            return $uuid;
        }

        $mediaModel = self::getMediaModel();

        $media = $mediaModel::where('filename', $uuid)
            ->orWhere('metadata->cdnUrl', 'like', '%'.$uuid.'%')
            ->first();

        if ($media && isset($media->metadata['cdnUrl'])) {
            return $media->metadata['cdnUrl'];
        }

        return 'https://ucarecdn.com/'.$uuid.'/';
    }

    private static function isValidCdnUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) && self::extractUuidFromString($url) !== null;
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

    public ?Field $field_model = null;

    public function hydrate(mixed $value, ?Model $model = null): mixed
    {

        if (empty($value)) {
            return null;
        }

        // Normalize value first
        if (is_string($value) && json_validate($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            }
        }

        // Try to hydrate from relation
        $hydratedFromModel = self::hydrateFromModel($model, $value, true);

        if ($hydratedFromModel !== null && ! empty($hydratedFromModel)) {
            // Check config to decide if we should return single or multiple
            $config = $this->field_model->config ?? $model->field->config ?? [];
            $isMultiple = $config['multiple'] ?? false;

            if ($isMultiple) {
                return $hydratedFromModel;
            }

            return $hydratedFromModel->first();
        }

        $mediaModel = self::getMediaModel();

        if (is_string($value) && ! json_validate($value)) {
            // Check if it's a ULID
            if (preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $value)) {
                $media = $mediaModel::where('ulid', $value)->first();

                // Check config to decide if we should return single or multiple
                $config = $this->field_model->config ?? $model->field->config ?? [];
                $isMultiple = $config['multiple'] ?? false;

                if ($isMultiple && $media) {
                    return new \Illuminate\Database\Eloquent\Collection([$media]);
                }

                return $media ? [$media] : $value;
            }

            // Check if it's a CDN URL - try to extract UUID and load Media
            if (filter_var($value, FILTER_VALIDATE_URL) && (str_contains($value, 'ucarecdn.com') || str_contains($value, 'ucarecd.net'))) {
                $uuid = self::extractUuidFromString($value);
                if ($uuid) {
                    $media = $mediaModel::where('filename', $uuid)->first();
                    if ($media) {
                        // Extract modifiers from URL if present
                        $cdnUrlModifiers = null;
                        $uuidPos = strpos($value, $uuid);
                        if ($uuidPos !== false) {
                            $modifiers = substr($value, $uuidPos + strlen($uuid));
                            if (! empty($modifiers) && $modifiers[0] === '/') {
                                $cdnUrlModifiers = substr($modifiers, 1);
                            } elseif (! empty($modifiers)) {
                                $cdnUrlModifiers = $modifiers;
                            }
                        }

                        $media->setAttribute('edit', [
                            'uuid' => $uuid,
                            'cdnUrl' => $value,
                            'cdnUrlModifiers' => $cdnUrlModifiers,
                        ]);

                        // Check config to decide if we should return single or multiple
                        $config = $this->field_model->config ?? $model->field->config ?? [];
                        $isMultiple = $config['multiple'] ?? false;

                        if ($isMultiple) {
                            return new \Illuminate\Database\Eloquent\Collection([$media]);
                        }

                        return [$media];
                    }
                }
            }

            return $value;
        }

        // Try manual hydration if relation hydration failed (e.g. pivot missing but media exists)
        $hydratedUlids = self::hydrateBackstageUlids($value);

        if ($hydratedUlids !== null) {
            // Check if we need to return a single item based on config, even for manual hydration
            // Priority: Local field model config -> Parent model field config
            $config = $this->field_model->config ?? $model->field->config ?? [];

            // hydrateBackstageUlids returns an array, so we check if single
            if (! ($config['multiple'] ?? false) && is_array($hydratedUlids) && ! empty($hydratedUlids)) {
                // Wrap in collection first to match expected behavior if we were to return collection,
                // but here we want single model
                return $hydratedUlids[0];
            }

            // If expected multiple, return collection
            return new \Illuminate\Database\Eloquent\Collection($hydratedUlids);
        }

        // If it looks like a list of ULIDs but failed to hydrate (e.g. media deleted),
        // return an empty Collection (or null if single) instead of the raw string array.
        if (is_array($value) && ! empty($value)) {
            $first = reset($value);
            $isString = is_string($first);
            $matches = $isString ? preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $first) : false;

            if ($isString && $matches) {
                $config = $this->field_model->config ?? $model->field->config ?? [];
                if (! ($config['multiple'] ?? false)) {
                    return null;
                }

                return new \Illuminate\Database\Eloquent\Collection;
            }
        }

        return $value;
    }

    public static function mapMediaToValue(mixed $media): array|string
    {
        if (! $media instanceof Model && ! is_array($media)) {
            return is_string($media) ? $media : [];
        }

        $source = 'unknown';
        if (is_array($media)) {
            $data = $media;
            $source = 'array';
        } else {
            $hasHydratedEdit = $media instanceof Model && array_key_exists('hydrated_edit', $media->getAttributes());
            $data = $hasHydratedEdit ? $media->getAttribute('hydrated_edit') : $media->getAttribute('edit');
            $source = $hasHydratedEdit ? 'hydrated_edit' : 'edit_accessor';

            // Prioritize pivot meta if loaded, as it contains usage-specific modifiers
            if ($media->relationLoaded('pivot') && $media->pivot && ! empty($media->pivot->meta)) {
                $pivotMeta = $media->pivot->meta;
                if (is_string($pivotMeta)) {
                    $pivotMeta = json_decode($pivotMeta, true);
                }

                // Merge pivot meta over existing data, or use it as primary if data is empty
                if (is_array($pivotMeta)) {
                    $data = ! empty($data) && is_array($data) ? array_merge($data, $pivotMeta) : $pivotMeta;
                    $source = 'pivot_meta_merged';
                }
            }

            $data = $data ?? $media->metadata;
            if (empty($data)) {
                $source = 'none';
            }

            if (is_string($data)) {
                $data = json_decode($data, true);
            }
        }

        if (is_array($data)) {
            // Extract modifiers from cdnUrl if missing
            if (isset($data['cdnUrl']) && ! isset($data['cdnUrlModifiers'])) {
                $cdnUrl = $data['cdnUrl'];
                // Extract UUID and modifiers from URL like: https://ucarecdn.com/{uuid}/{modifiers}
                if (preg_match('/([a-f0-9-]{36})\/(.+)$/', $cdnUrl, $matches)) {
                    $modifiers = $matches[2];
                    // Clean up trailing slash
                    $modifiers = rtrim($modifiers, '/');
                }
            }

            // Append modifiers to cdnUrl if present and not already part of the URL
            if (isset($data['cdnUrl'], $data['cdnUrlModifiers']) && ! str_contains($data['cdnUrl'], '/-/')) {
                $modifiers = $data['cdnUrlModifiers'];
                if (str_starts_with($modifiers, '/')) {
                    $modifiers = substr($modifiers, 1);
                }

                // Ensure cdnUrl includes modifiers
                $data['cdnUrl'] = rtrim($data['cdnUrl'], '/').'/'.$modifiers;
                if (! str_ends_with($data['cdnUrl'], '/')) {
                    $data['cdnUrl'] .= '/';
                }
            }
        }

        return is_array($data) ? $data : [];
    }

    private static function hydrateFromModel(?Model $model, mixed $value = null, bool $returnModels = false): mixed
    {
        if (! $model || ! method_exists($model, 'media')) {
            return null;
        }

        $ulids = null;
        if (is_array($value) && ! empty($value)) {
            $ulids = array_filter(Arr::flatten($value), function ($item) {
                return is_string($item) && preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $item);
            });
            $ulids = array_values($ulids);
        }

        $mediaQuery = $model->media()->withPivot(['meta', 'position'])->distinct();

        if (! empty($ulids)) {
            $mediaQuery->whereIn('media_ulid', $ulids)
                ->orderByRaw('FIELD(media_ulid, '.implode(',', array_fill(0, count($ulids), '?')).')', $ulids);
        }

        $media = $mediaQuery->get()->unique('ulid');

        $media->each(function ($m) use ($model) {
            $mediaUlid = $m->ulid ?? 'UNKNOWN';

            if ($m->pivot && $m->pivot->meta) {
                $pivotMeta = is_string($m->pivot->meta) ? json_decode($m->pivot->meta, true) : $m->pivot->meta;

                if (is_array($pivotMeta)) {
                    $m->setAttribute('hydrated_edit', $pivotMeta);
                    if ($model) {
                        $contextModel = clone $model;
                        $contextModel->setRelation('pivot', $m->pivot);
                        $m->setRelation('edits', new \Illuminate\Database\Eloquent\Collection([$contextModel]));
                    }

                }
            } else {

            }
        });

        if ($returnModels) {
            return $media;
        }

        return json_encode($media->map(fn ($m) => self::mapMediaToValue($m))->values()->all());
    }

    private static function resolveMediaFromMixedValue(mixed $item): ?Model
    {
        $mediaModel = self::getMediaModel();

        if ($item instanceof Model) {
            return $item;
        }

        if (is_string($item) && $item !== '') {
            if (filter_var($item, FILTER_VALIDATE_URL)) {
                $uuid = self::extractUuidFromString($item);

                return $uuid ? $mediaModel::where('filename', $uuid)->first() : null;
            }

            if (preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $item)) {
                return $mediaModel::where('ulid', $item)->first();
            }

            $uuid = self::extractUuidFromString($item);

            return $uuid ? $mediaModel::where('filename', $uuid)->first() : null;
        }

        if (is_array($item)) {
            $ulid = $item['ulid'] ?? $item['id'] ?? $item['media_ulid'] ?? null;
            if (is_string($ulid) && $ulid !== '') {
                $media = $mediaModel::where('ulid', $ulid)->first();
                if ($media) {
                    return $media;
                }
            }

            $uuid = $item['uuid'] ?? ($item['fileInfo']['uuid'] ?? null);
            if (is_string($uuid) && $uuid !== '') {
                $media = $mediaModel::where('filename', $uuid)->first();
                if ($media) {
                    return $media;
                }

                if (preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $uuid)) {
                    return $mediaModel::where('ulid', $uuid)->first();
                }
            }

            $cdnUrl = $item['cdnUrl'] ?? ($item['fileInfo']['cdnUrl'] ?? null) ?? null;
            if (is_string($cdnUrl) && filter_var($cdnUrl, FILTER_VALIDATE_URL)) {
                $uuid = self::extractUuidFromString($cdnUrl);

                return $uuid ? $mediaModel::where('filename', $uuid)->first() : null;
            }
        }

        return null;
    }

    private static function hydrateBackstageUlids(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        if (array_is_list($value)) {
            $hydrated = [];
            foreach ($value as $item) {
                $media = self::resolveMediaFromMixedValue($item);
                if ($media) {
                    $hydrated[] = $media->load('edits');
                }
            }

            return ! empty($hydrated) ? $hydrated : null;
        } elseif (is_array($value)) {
            $media = self::resolveMediaFromMixedValue($value);
            if ($media) {
                return [$media->load('edits')];
            }
        }

        $mediaModel = self::getMediaModel();
        $potentialUlids = array_filter(Arr::flatten($value), function ($item) {
            return is_string($item) && ! json_validate($item);
        });

        if (empty($potentialUlids)) {
            return null;
        }

        $mediaItems = $mediaModel::whereIn('ulid', $potentialUlids)->get();

        $resolve = function ($item) use ($mediaItems, &$resolve) {
            if (is_array($item)) {
                return array_map($resolve, $item);
            }

            if (is_string($item) && ! json_validate($item)) {
                $media = $mediaItems->firstWhere('ulid', $item);
                if ($media) {
                    return $media->load('edits');
                }

                return null;
            }

            return $item;
        };

        $hydrated = array_map($resolve, $value);
        $hydrated = array_values(array_filter($hydrated));

        return ! empty($hydrated) ? $hydrated : null;
    }
}
