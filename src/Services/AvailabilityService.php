<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Answers the only question that really matters in a reservation system:
 * "is this room free between these two dates?"
 *
 * Two date ranges overlap if and only if
 *     existing.check_in  <  requested.check_out
 * AND existing.check_out >  requested.check_in
 *
 * The comparisons are STRICT (`<` and `>`) rather than `<=` / `>=` because
 * check-out day is a turnover day: a room vacated on the 5th is sellable again
 * on the 5th. Using `<=` here silently loses one sellable night per booking.
 */
final class AvailabilityService
{
    /**
     * Housekeeping statuses that make a room unsellable.
     *
     * Only `maintenance` blocks a sale. `occupied` and `cleaning` describe the
     * room RIGHT NOW, not on the requested dates — the overlap check below is
     * what handles date conflicts.
     *
     * The previous filter was `status IN ('available','cleaning')`, which meant a
     * room with a guest currently in it could not be booked for next month. Every
     * occupied room silently disappeared from search results.
     */
    private const UNSELLABLE_STATUSES = "'maintenance'";

    /**
     * Rooms of a given type that are free for the whole requested range.
     */
    public function availableRooms(int $roomTypeId, string $checkIn, string $checkOut): array
    {
        $sql = "
            SELECT r.id, r.room_number, r.floor, r.status
            FROM rooms r
            WHERE r.room_type_id = :type_id
              AND r.is_active   = 1
              AND r.status NOT IN (" . self::UNSELLABLE_STATUSES . ")
              AND NOT EXISTS (
                  SELECT 1
                  FROM bookings b
                  WHERE b.room_id = r.id
                    AND b.status IN ('pending', 'confirmed', 'checked_in')
                    AND b.check_in  <  :check_out
                    AND b.check_out >  :check_in
              )
            ORDER BY r.floor, r.room_number";

        return Database::getInstance()->query($sql, [
            ':type_id'   => $roomTypeId,
            ':check_in'  => $checkIn,
            ':check_out' => $checkOut,
        ])->fetchAll();
    }

    /**
     * Count only — cheaper than fetching every row when all we render is a badge.
     */
    public function availableCount(int $roomTypeId, string $checkIn, string $checkOut): int
    {
        $sql = "
            SELECT COUNT(*)
            FROM rooms r
            WHERE r.room_type_id = :type_id
              AND r.is_active   = 1
              AND r.status NOT IN (" . self::UNSELLABLE_STATUSES . ")
              AND NOT EXISTS (
                  SELECT 1
                  FROM bookings b
                  WHERE b.room_id = r.id
                    AND b.status IN ('pending', 'confirmed', 'checked_in')
                    AND b.check_in  <  :check_out
                    AND b.check_out >  :check_in
              )";

        return (int) Database::getInstance()->query($sql, [
            ':type_id'   => $roomTypeId,
            ':check_in'  => $checkIn,
            ':check_out' => $checkOut,
        ])->fetchColumn();
    }

    /**
     * Availability counts for MANY room types in one query.
     *
     * The rooms listing page previously called availableCount() inside its render
     * loop — one extra query per card (an N+1). This resolves the whole page in a
     * single round trip.
     *
     * @param  int[] $roomTypeIds
     * @return array<int,int>  room_type_id => available count
     */
    public function availableCountsForTypes(array $roomTypeIds, string $checkIn, string $checkOut): array
    {
        $ids = array_values(array_unique(array_map('intval', $roomTypeIds)));
        if ($ids === []) {
            return [];
        }

        // Build one named placeholder per id. The ids are cast to int above, so
        // no user-supplied text is ever interpolated into the statement.
        $placeholders = [];
        $params = [':check_in' => $checkIn, ':check_out' => $checkOut];
        foreach ($ids as $i => $id) {
            $placeholders[] = ":t{$i}";
            $params[":t{$i}"] = $id;
        }

        $sql = "
            SELECT r.room_type_id, COUNT(*) AS free_rooms
            FROM rooms r
            WHERE r.room_type_id IN (" . implode(',', $placeholders) . ")
              AND r.is_active = 1
              AND r.status NOT IN (" . self::UNSELLABLE_STATUSES . ")
              AND NOT EXISTS (
                  SELECT 1
                  FROM bookings b
                  WHERE b.room_id = r.id
                    AND b.status IN ('pending', 'confirmed', 'checked_in')
                    AND b.check_in  <  :check_out
                    AND b.check_out >  :check_in
              )
            GROUP BY r.room_type_id";

        $rows = Database::getInstance()->query($sql, $params)->fetchAll();

        // Types with zero free rooms are absent from a GROUP BY result, so start
        // from a zero-filled map rather than trusting the query to list them.
        $counts = array_fill_keys($ids, 0);
        foreach ($rows as $row) {
            $counts[(int) $row['room_type_id']] = (int) $row['free_rooms'];
        }

        return $counts;
    }

    /**
     * Is one specific room free? Used by the front desk and as the pre-check
     * before BookingService takes its row lock.
     */
    public function isRoomAvailable(int $roomId, string $checkIn, string $checkOut): bool
    {
        $sql = "
            SELECT COUNT(*)
            FROM rooms r
            WHERE r.id = :room_id
              AND r.is_active = 1
              AND r.status NOT IN (" . self::UNSELLABLE_STATUSES . ")
              AND NOT EXISTS (
                  SELECT 1
                  FROM bookings b
                  WHERE b.room_id = r.id
                    AND b.status IN ('pending', 'confirmed', 'checked_in')
                    AND b.check_in  <  :check_out
                    AND b.check_out >  :check_in
              )";

        return (int) Database::getInstance()->query($sql, [
            ':room_id'   => $roomId,
            ':check_in'  => $checkIn,
            ':check_out' => $checkOut,
        ])->fetchColumn() > 0;
    }

    /**
     * The earliest date this room type frees up, for the "sold out" empty state.
     */
    public function nextAvailableDate(int $roomTypeId, string $fromDate): ?string
    {
        $sql = "
            SELECT MIN(b.check_out) AS next_free
            FROM bookings b
            JOIN rooms r ON r.id = b.room_id
            WHERE r.room_type_id = :type_id
              AND r.is_active = 1
              AND b.status IN ('pending', 'confirmed', 'checked_in')
              AND b.check_out >= :from_date";

        $value = Database::getInstance()->query($sql, [
            ':type_id'   => $roomTypeId,
            ':from_date' => $fromDate,
        ])->fetchColumn();

        return $value ? (string) $value : null;
    }
}
