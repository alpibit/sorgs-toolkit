<?php
class FormValidator
{
    private $input;
    private $errors = [];

    public function __construct(array $input)
    {
        $this->input = $input;
    }

    public function text($field, $label, $required = false, $maxCharacters = null, $maxBytes = 65535)
    {
        $value = array_key_exists($field, $this->input) ? $this->input[$field] : '';
        if (!is_string($value)) {
            $this->errors[$field] = "$label must be text.";
            return '';
        }
        if ($required && trim($value) === '') {
            $this->errors[$field] = "Please enter $label.";
        } elseif (!preg_match('//u', $value)) {
            $this->errors[$field] = "$label contains invalid text.";
        } elseif ($maxBytes !== null && strlen($value) > $maxBytes) {
            $this->errors[$field] = "$label exceeds the storage limit of $maxBytes bytes.";
        } elseif ($maxCharacters !== null && preg_match_all('/./us', $value) > $maxCharacters) {
            $this->errors[$field] = "$label must be no longer than $maxCharacters characters.";
        }
        return $value;
    }

    public function integer($field, $label, $minimum = -2147483648, $maximum = 2147483647, $required = true)
    {
        $value = $this->text($field, $label, $required);
        $number = trim($value);
        if (!isset($this->errors[$field]) && ($required || $number !== '')) {
            if (!preg_match('/^[+-]?[0-9]+$/D', $number) || $number < $minimum || $number > $maximum) {
                $this->errors[$field] = "$label must be a whole number between $minimum and $maximum.";
            }
        }
        return $value;
    }

    public function email($field, $label, $required = false, $maxCharacters = null)
    {
        $value = trim($this->text($field, $label, $required, $maxCharacters));
        if (!isset($this->errors[$field]) && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = "Please enter a valid $label.";
        }
        return $value;
    }

    public function addError($field, $message)
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = $message;
        }
    }

    public function getErrors()
    {
        return array_values($this->errors);
    }
}
