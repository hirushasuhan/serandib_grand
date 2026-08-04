<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class ContactMessage extends Model
{
    protected static string $table = 'contact_messages';
    protected static array  $fillable = [
        'name', 'email', 'subject', 'message', 'status', 'ip_address'
    ];

    public function rules(): array
    {
        return [
            'name'    => 'required|min:2|max:120',
            'email'   => 'required|email|max:160',
            'subject' => 'required|min:3|max:160',
            'message' => 'required|min:10|max:2000'
        ];
    }
}
