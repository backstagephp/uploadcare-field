<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Decode all JSON strings recursively to fix double-encoded data
     */
    private function decodeAllJsonStrings($data, $path = '')
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $currentPath = $path === '' ? $key : $path . '.' . $key;
                if (is_string($value)) {
                    $decoded = $value;
                    $decodeCount = 0;
                    while (is_string($decoded)) {
                        $json = json_decode($decoded, true);
                        if ($json !== null && (is_array($json) || is_object($json))) {
                            $decoded = $json;
                            $decodeCount++;
                        } else {
                            break;
                        }
                    }
                    if ($decodeCount > 0) {
                        logger("Decoded '$key' at $currentPath ($decodeCount times)");
                        $data[$key] = $this->decodeAllJsonStrings($decoded, $currentPath);
                    }
                } elseif (is_array($value)) {
                    $data[$key] = $this->decodeAllJsonStrings($value, $currentPath);
                }
            }
        }

        return $data;
    }

    /**
     * Check if a string contains double-encoded JSON and decode it
     */
    private function decodeDoubleEncodedJson($value)
    {
        if (! is_string($value)) {
            return $value;
        }

        $decoded = $value;
        $decodeCount = 0;

        while (is_string($decoded)) {
            $json = json_decode($decoded, true);
            if ($json !== null && (is_array($json) || is_object($json))) {
                $decoded = $json;
                $decodeCount++;
            } else {
                break;
            }
        }

        if ($decodeCount > 1) {
            logger("Decoded double-encoded JSON ($decodeCount times)");

            return $this->decodeAllJsonStrings($decoded);
        }

        return $value;
    }

    /**
     * Run the migrations.
     */
    public function up()
    {
        // Only run if the tables exist
        if (! Schema::hasTable('content_field_values') && ! Schema::hasTable('settings')) {
            return;
        }

        // Update content_field_values table if it exists
        if (Schema::hasTable('content_field_values')) {
            DB::table('content_field_values')->orderBy('ulid')->chunk(100, function ($rows) {
                foreach ($rows as $row) {
                    $value = $row->value;

                    // First check if the entire value is double-encoded
                    $decodedValue = $this->decodeDoubleEncodedJson($value);
                    if ($decodedValue !== $value) {
                        DB::table('content_field_values')
                            ->where('ulid', $row->ulid)
                            ->update(['value' => json_encode($decodedValue)]);
                        logger("Updated content_field_values (top-level): {$row->ulid}");

                        continue;
                    }

                    // Then check nested values
                    $decoded = json_decode($value, true);
                    if (is_array($decoded)) {
                        $newDecoded = $this->decodeAllJsonStrings($decoded);
                        if ($newDecoded !== $decoded) {
                            DB::table('content_field_values')
                                ->where('ulid', $row->ulid)
                                ->update(['value' => json_encode($newDecoded)]);
                            logger("Updated content_field_values (nested): {$row->ulid}");
                        }
                    }
                }
            });
        }

        // Update settings table if it exists
        if (Schema::hasTable('settings')) {
            DB::table('settings')->orderBy('ulid')->chunk(100, function ($rows) {
                foreach ($rows as $row) {
                    $values = $row->values;
                    if ($values === null) {
                        continue;
                    }

                    // First check if the entire value is double-encoded
                    $decodedValues = $this->decodeDoubleEncodedJson($values);
                    if ($decodedValues !== $values) {
                        DB::table('settings')
                            ->where('ulid', $row->ulid)
                            ->update(['values' => json_encode($decodedValues)]);
                        logger("Updated settings (top-level): {$row->ulid}");

                        continue;
                    }

                    // Then check nested values
                    $decoded = json_decode($values, true);
                    if (is_array($decoded)) {
                        $newDecoded = $this->decodeAllJsonStrings($decoded);
                        if ($newDecoded !== $decoded) {
                            DB::table('settings')
                                ->where('ulid', $row->ulid)
                                ->update(['values' => json_encode($newDecoded)]);
                            logger("Updated settings (nested): {$row->ulid}");
                        }
                    }
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // This migration is not reversible
        // The data has been permanently fixed
    }
};
