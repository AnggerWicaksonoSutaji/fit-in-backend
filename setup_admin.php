<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

try {
    DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('free', 'premium', 'admin') DEFAULT 'free'");
    echo "Table users modified successfully.\n";
} catch (\Exception $e) {
    echo "Error modifying table: " . $e->getMessage() . "\n";
}

$admin = User::where('name', 'admin')->first();
if (!$admin) {
    User::create([
        'name' => 'admin',
        'email' => 'admin@fitin.com',
        'password' => Hash::make('password123'),
        'role' => 'admin'
    ]);
    echo "Admin user created successfully.\n";
} else {
    $admin->update(['role' => 'admin', 'password' => Hash::make('password123')]);
    echo "Admin user updated successfully.\n";
}
