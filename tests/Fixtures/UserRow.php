<?php

namespace JDZ\Database\Tests\Fixtures;

/**
 * A row class for loadObject() / loadObjectList() with a custom class.
 */
class UserRow
{
    public $id;
    public $name;
    public $email;
    public $age;

    public function label(): string
    {
        return $this->name . ' <' . $this->email . '>';
    }
}
