<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Criar usuário admin padrão
        User::updateOrCreate(
            ['email' => 'admin@nexxivo.com'],
            [
                'name' => 'Administrador',
                'email' => 'admin@nexxivo.com',
                'password' => Hash::make('admin123'), // Senha padrão - ALTERE APÓS O PRIMEIRO LOGIN!
                'is_admin' => true,
            ]
        );

        $this->command->info('Usuário admin criado com sucesso!');
        $this->command->info('E-mail: admin@nexxivo.com');
        $this->command->info('Senha: admin123');
        $this->command->warn('IMPORTANTE: Altere a senha após o primeiro login!');
    }
}
