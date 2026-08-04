<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Rule-based server-side validator.
 *
 * This is the ONLY gate that matters. The HTML5 attributes and
 * assets/js/validation.js exist purely to give the user fast feedback; both are
 * trivially bypassed with curl or DevTools, so every rule is re-checked here.
 */
final class Validator
{
    private array $data;
    private array $rules;
    private array $errors = [];

    /**
     * Rules that treat the value as a NUMBER rather than a string.
     * Needed so `min:8` on a password means "8 characters", not "value >= 8".
     */
    private const NUMERIC_RULES = ['integer', 'numeric', 'decimal'];

    /** Every rule name this class understands. Anything else is a typo. */
    private const KNOWN_RULES = [
        'nullable', 'required', 'email', 'phone', 'nic_passport', 'string',
        'min', 'max', 'min_length', 'max_length', 'integer', 'numeric', 'decimal',
        'date', 'date_format', 'after', 'after_or_equal', 'before_days',
        'max_nights', 'confirmed', 'in', 'unique', 'exists', 'password',
        'accepted', 'regex', 'boolean',
    ];

    private function __construct(array $data, array $rules)
    {
        $this->data  = $data;
        $this->rules = $rules;
        $this->validate();
    }

    public static function make(array $data, array $rules): self
    {
        return new self($data, $rules);
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * The subset of input covered by a rule.
     *
     * Password fields are returned untrimmed: silently stripping whitespace
     * would change the secret the user actually typed.
     */
    public function validated(): array
    {
        $validatedData = [];
        foreach (array_keys($this->rules) as $field) {
            if (!array_key_exists($field, $this->data)) {
                continue;
            }
            $value = $this->data[$field];
            if (is_string($value) && !$this->isSecretField($field)) {
                $value = trim($value);
            }
            $validatedData[$field] = $value;
        }
        return $validatedData;
    }

    private function isSecretField(string $field): bool
    {
        return str_contains($field, 'password');
    }

    private function validate(): void
    {
        foreach ($this->rules as $field => $ruleset) {
            $raw = $this->data[$field] ?? null;

            $value = $raw;
            if (is_string($value) && !$this->isSecretField($field)) {
                $value = trim($value);
            }

            $ruleArray = is_array($ruleset) ? $ruleset : explode('|', $ruleset);

            // A nullable field that was left blank skips all remaining rules.
            if (in_array('nullable', $ruleArray, true) && ($value === null || $value === '')) {
                continue;
            }

            // Does this ruleset describe a number? Decides whether min/max mean
            // "value range" or "character length".
            $treatAsNumber = false;
            foreach ($ruleArray as $r) {
                if (in_array(explode(':', $r)[0], self::NUMERIC_RULES, true)) {
                    $treatAsNumber = true;
                    break;
                }
            }

            foreach ($ruleArray as $rule) {
                if ($rule === 'nullable' || $rule === '') {
                    continue;
                }

                $params = [];
                if (str_contains($rule, ':')) {
                    [$ruleName, $paramStr] = explode(':', $rule, 2);
                    $params = explode(',', $paramStr);
                } else {
                    $ruleName = $rule;
                }

                // Fail loudly on a misspelled rule. Previously an unknown rule
                // fell through the switch silently, so a field the developer
                // believed was validated was in fact wide open.
                if (!in_array($ruleName, self::KNOWN_RULES, true)) {
                    Logger::warning("Validator: unknown rule '{$ruleName}' on field '{$field}'");
                    continue;
                }

                $this->applyRule($field, $value, $ruleName, $params, $treatAsNumber);
            }
        }
    }

    private function applyRule(
        string $field,
        mixed $value,
        string $rule,
        array $params,
        bool $treatAsNumber = false
    ): void {
        $label = str_replace('_', ' ', $field);

        switch ($rule) {
            case 'required':
                // Careful: "0" is a legitimate value, so compare strictly.
                if ($value === null || $value === '' || (is_array($value) && $value === [])) {
                    $this->addError($field, "The {$label} field is required.");
                }
                break;

            case 'accepted':
                if (!in_array((string) $value, ['1', 'on', 'yes', 'true'], true)) {
                    $this->addError($field, "You must accept the {$label}.");
                }
                break;

            case 'boolean':
                if (!in_array((string) $value, ['0', '1', 'true', 'false', 'on', 'off', ''], true)) {
                    $this->addError($field, "The {$label} field must be true or false.");
                }
                break;

            case 'string':
                if ($value !== null && !is_string($value)) {
                    $this->addError($field, "The {$label} field must be text.");
                }
                break;

            case 'email':
                if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, 'Enter a valid email address.');
                }
                break;

            case 'phone':
                if ($value !== null && $value !== '') {
                    // Normalise separators before testing: users type 077-123 4567.
                    $digits = preg_replace('/[\s\-()]/', '', (string) $value);
                    if (!preg_match('/^(?:\+94|0)7\d{8}$/', (string) $digits)) {
                        $this->addError($field, 'Enter a valid Sri Lankan mobile number, e.g. 0712345678.');
                    }
                }
                break;

            case 'nic_passport':
                if ($value !== null && $value !== '') {
                    $isNic      = preg_match('/^(\d{9}[VvXx]|\d{12})$/', (string) $value);
                    $isPassport = preg_match('/^[A-Za-z]\d{7,8}$/', (string) $value);
                    if (!$isNic && !$isPassport) {
                        $this->addError($field, 'Enter a valid Sri Lankan NIC or Passport number.');
                    }
                }
                break;

            case 'min':
            case 'min_length':
                $min = (int) ($params[0] ?? 0);
                // FIX: previously `is_numeric($value)` was tested first, so the
                // numeric password "99999" satisfied min:8 (99999 >= 8) despite
                // being only 5 characters. Length vs range is now decided by the
                // ruleset, not by whether the value happens to look like a number.
                if ($rule === 'min_length' || !$treatAsNumber) {
                    if (mb_strlen((string) $value) < $min) {
                        $this->addError($field, "The {$label} must be at least {$min} characters long.");
                    }
                } elseif (is_numeric($value) && (float) $value < $min) {
                    $this->addError($field, "The {$label} must be at least {$min}.");
                }
                break;

            case 'max':
            case 'max_length':
                $max = (int) ($params[0] ?? 0);
                if ($rule === 'max_length' || !$treatAsNumber) {
                    if (mb_strlen((string) $value) > $max) {
                        $this->addError($field, "The {$label} must not exceed {$max} characters.");
                    }
                } elseif (is_numeric($value) && (float) $value > $max) {
                    $this->addError($field, "The {$label} must not exceed {$max}.");
                }
                break;

            case 'password':
                // Policy from the specification: 8+ chars with upper, lower,
                // digit and symbol, and not an obvious guess.
                $pw = (string) $value;
                $problems = [];
                if (mb_strlen($pw) < PASSWORD_MIN_LENGTH) $problems[] = 'at least ' . PASSWORD_MIN_LENGTH . ' characters';
                if (!preg_match('/[A-Z]/', $pw))           $problems[] = 'one uppercase letter';
                if (!preg_match('/[a-z]/', $pw))           $problems[] = 'one lowercase letter';
                if (!preg_match('/\d/', $pw))              $problems[] = 'one number';
                if (!preg_match('/[^A-Za-z0-9]/', $pw))    $problems[] = 'one symbol';

                if ($problems) {
                    $this->addError($field, 'Password needs ' . implode(', ', $problems) . '.');
                    break;
                }

                $blocklist = ['password1!', 'qwerty@123', 'password@123', 'admin@1234', 'welcome@123', 'serendib@123'];
                $emailLocal = strtolower(explode('@', (string) ($this->data['email'] ?? ''))[0]);
                if (in_array(strtolower($pw), $blocklist, true)
                    || ($emailLocal !== '' && stripos($pw, $emailLocal) !== false)) {
                    $this->addError($field, 'That password is too easy to guess. Please choose another.');
                }
                break;

            case 'integer':
                if ($value !== null && $value !== '' && filter_var($value, FILTER_VALIDATE_INT) === false) {
                    $this->addError($field, "The {$label} must be a whole number.");
                }
                break;

            case 'numeric':
            case 'decimal':
                if ($value !== null && $value !== '' && !is_numeric($value)) {
                    $this->addError($field, "The {$label} must be a number.");
                }
                break;

            case 'date':
                if ($value !== null && $value !== '' && strtotime((string) $value) === false) {
                    $this->addError($field, "The {$label} must be a valid date.");
                }
                break;

            case 'date_format':
                // Strict format check. `strtotime` happily accepts "next tuesday",
                // which then reaches MySQL as garbage, so dates that will be
                // stored must go through this rule.
                $format = $params[0] ?? 'Y-m-d';
                $parsed = \DateTime::createFromFormat($format, (string) $value);
                if (!$parsed || $parsed->format($format) !== (string) $value) {
                    $this->addError($field, "The {$label} must be a valid date (format {$format}).");
                }
                break;

            case 'after':
                $targetField = $params[0] ?? '';
                $targetVal   = $this->data[$targetField] ?? $targetField;
                if ($value && $targetVal) {
                    if (strtotime((string) $value) <= strtotime((string) $targetVal)) {
                        $this->addError($field, 'The ' . $label . ' must be after the ' . str_replace('_', ' ', $targetField) . '.');
                    }
                }
                break;

            case 'after_or_equal':
                $target    = $params[0] ?? '';
                $targetVal = ($target === 'today') ? date('Y-m-d') : ($this->data[$target] ?? $target);
                if ($value && $targetVal) {
                    if (strtotime((string) $value) < strtotime((string) $targetVal)) {
                        $this->addError($field, $target === 'today'
                            ? "The {$label} cannot be in the past."
                            : "The {$label} must be on or after the " . str_replace('_', ' ', $target) . '.');
                    }
                }
                break;

            case 'before_days':
                // Stops someone reserving a room three years out.
                $days = (int) ($params[0] ?? 365);
                if ($value && strtotime((string) $value) > strtotime("+{$days} days")) {
                    $this->addError($field, "The {$label} cannot be more than {$days} days ahead.");
                }
                break;

            case 'max_nights':
                // Applied to check_out; compares against check_in in the same payload.
                $maxNights = (int) ($params[0] ?? 30);
                $start = $this->data['check_in'] ?? null;
                if ($value && $start) {
                    $t1 = strtotime((string) $start);
                    $t2 = strtotime((string) $value);
                    if ($t1 && $t2 && (($t2 - $t1) / 86400) > $maxNights) {
                        $this->addError($field, "A single stay cannot exceed {$maxNights} nights.");
                    }
                }
                break;

            case 'confirmed':
                // Compare the RAW values so a trailing space in a password is a
                // genuine mismatch rather than being silently trimmed away.
                $confirmField = $field . '_confirm';
                $original     = $this->data[$field] ?? null;
                if (!isset($this->data[$confirmField]) || $original !== $this->data[$confirmField]) {
                    $this->addError($field, "The {$label} confirmation does not match.");
                }
                break;

            case 'regex':
                $pattern = $params[0] ?? '';
                if ($value !== null && $value !== '' && $pattern !== '' && !preg_match($pattern, (string) $value)) {
                    $this->addError($field, "The {$label} format is invalid.");
                }
                break;

            case 'in':
                if ($value !== null && $value !== '' && !in_array((string) $value, $params, true)) {
                    $this->addError($field, "The selected {$label} is invalid.");
                }
                break;

            case 'unique':
                if ($value !== null && $value !== '' && count($params) >= 2) {
                    $table  = self::safeIdentifier($params[0]);
                    $column = self::safeIdentifier($params[1]);
                    $ignoreId = $params[2] ?? null;

                    $sql   = "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = :val";
                    $binds = [':val' => $value];

                    if ($ignoreId !== null && $ignoreId !== '') {
                        $sql .= ' AND id != :ignore_id';
                        $binds[':ignore_id'] = (int) $ignoreId;
                    }

                    if ((int) Database::getInstance()->query($sql, $binds)->fetchColumn() > 0) {
                        $this->addError($field, "That {$label} is already in use.");
                    }
                }
                break;

            case 'exists':
                if ($value !== null && $value !== '' && count($params) >= 2) {
                    $table  = self::safeIdentifier($params[0]);
                    $column = self::safeIdentifier($params[1]);

                    $sql = "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = :val";
                    if ((int) Database::getInstance()->query($sql, [':val' => $value])->fetchColumn() === 0) {
                        $this->addError($field, "The selected {$label} does not exist.");
                    }
                }
                break;
        }
    }

    /**
     * Table and column names cannot be bound as parameters, so they are
     * whitelisted by shape instead. Defence in depth: today these come from
     * developer-written rule strings, but if a rule is ever built from request
     * data this stops it becoming SQL injection.
     */
    private static function safeIdentifier(string $identifier): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new \InvalidArgumentException('Unsafe SQL identifier in validation rule.');
        }
        return $identifier;
    }

    private function addError(string $field, string $message): void
    {
        // First error per field only — that is the one the user should fix next.
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = $message;
        }
    }
}
