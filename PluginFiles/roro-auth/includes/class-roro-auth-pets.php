<?php
/**
 * User pet management for the MECE RORO Auth plugin.
 *
 * This lightweight helper encapsulates reading and writing the pet
 * information stored in a user's meta.  Each pet is represented as
 * an associative array with a unique `id` and user supplied fields.
 * The current representative pet is stored separately.  These
 * functions are utilised by the REST API controller when serving
 * requests to fetch and update a user's profile.
 */
class Roro_Auth_Pets {
    /**
     * Fetch all pets for a given user.
     *
     * @param int $user_id
     * @return array<int,array<string,mixed>>
     */
    public static function get_pets(int $user_id): array {
        $pets = get_user_meta($user_id, 'roro_pets', true);
        return is_array($pets) ? array_values($pets) : [];
    }

    /**
     * Persist a list of pets for a user.
     *
     * Performs basic normalisation and sanitisation on each entry.
     * Missing ids are generated on the fly.  The caller is
     * responsible for sending a full list of pets; partial updates
     * should be handled by reading the current list, modifying it and
     * then writing it back via this function.
     *
     * @param int   $user_id
     * @param array<int,array<string,mixed>> $pets
     * @return void
     */
    public static function save_pets(int $user_id, array $pets): void {
        $normalised = [];
        foreach ($pets as $p) {
            $normalised[] = [
                'id'    => sanitize_text_field($p['id'] ?? ('p_' . wp_generate_password(8, false))),
                'name'  => sanitize_text_field($p['name'] ?? ''),
                'breed' => sanitize_text_field($p['breed'] ?? ''),
                'age'   => isset($p['age']) ? max(0, (int) $p['age']) : 0,
                'notes' => sanitize_textarea_field($p['notes'] ?? ''),
            ];
        }
        update_user_meta($user_id, 'roro_pets', $normalised);
    }

    /**
     * Set which pet is representative for a user.
     *
     * Throws an exception if the requested id does not exist in the
     * user's current list of pets.  The caller should catch
     * exceptions and return an error via the REST API.
     *
     * @param int    $user_id
     * @param string $pet_id
     * @throws RuntimeException
     * @return void
     */
    public static function set_representative(int $user_id, string $pet_id): void {
        $exists = false;
        foreach (self::get_pets($user_id) as $p) {
            if (isset($p['id']) && $p['id'] === $pet_id) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            throw new RuntimeException(Roro_Auth_I18n::t('pet_not_found'));
        }
        update_user_meta($user_id, 'roro_rep_pet_id', sanitize_text_field($pet_id));
    }
}