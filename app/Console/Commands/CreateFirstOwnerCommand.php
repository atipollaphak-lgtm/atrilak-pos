<?php

namespace App\Console\Commands;

use App\Services\FirstOwnerProvisioningService;
use DomainException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Throwable;

class CreateFirstOwnerCommand extends Command
{
    protected $signature = 'atrilak:owner:create';

    protected $description = 'Create the first ATRILAK POS Owner account';

    public function handle(FirstOwnerProvisioningService $service): int
    {
        if ($service->ownerExists()) {
            $this->error('An Owner already exists. No changes were made.');

            return self::FAILURE;
        }

        $data = [
            'name' => $this->ask('Name'),
            'email' => $this->ask('Email'),
            'password' => $this->secret('Password'),
            'password_confirmation' => $this->secret('Confirm password'),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $validated = $validator->validated();

        try {
            $owner = $service->create(
                $validated['name'],
                $validated['email'],
                $validated['password'],
            );
        } catch (DomainException $exception) {
            $this->error('An Owner already exists. No changes were made.');

            return self::FAILURE;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Unable to create the Owner. No changes were made.');

            return self::FAILURE;
        }

        $this->info("Owner account created for {$owner->email}.");

        return self::SUCCESS;
    }
}
