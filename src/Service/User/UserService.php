<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Exception\User;
use Firebase\JWT\JWT;

final class UserService extends Base
{
    public function getUsersByPage(
        int $page,
        int $perPage,
        ?string $name,
        ?string $email
    ): array {
        if ($page < 1) {
            $page = 1;
        }
        if ($perPage < 1) {
            $perPage = self::DEFAULT_PER_PAGE_PAGINATION;
        }

        return $this->userRepository->getUsersByPage(
            $page,
            $perPage,
            $name,
            $email
        );
    }

    public function getAll(): array
    {
        return $this->userRepository->getAll();
    }

    public function getOne(int $userId): object
    {
        if (self::isRedisEnabled() === true) {
            $user = $this->getUserFromCache($userId);
        } else {
            $user = $this->getUserFromDb($userId)->getData();
        }

        return $user;
    }

    public function search(string $usersName): array
    {
        return $this->userRepository->search($usersName);
    }

    public function create(array $input): object
    {
        $data = $this->validateUserData($input);
        /** @var \App\Entity\User $user */
        $user = $this->userRepository->create($data);
        if (self::isRedisEnabled() === true) {
            $this->saveInCache((int) $user->getId(), $user->getData());
        }

        return $user->getData();
    }

    public function update(array $input, int $userId): object
    {
        $user = $this->getUserFromDb($userId);
        $data = json_decode((string) json_encode($input), false);
        if (! isset($data->name) && ! isset($data->email)) {
            throw new User('Enter the data to update the user.', 400);
        }
        if (isset($data->name)) {
            $user->updateName(self::validateUserName($data->name));
        }
        if (isset($data->email)) {
            $user->updateEmail(self::validateEmail($data->email));
        }
        /** @var \App\Entity\User $users */
        $users = $this->userRepository->update($user);
        if (self::isRedisEnabled() === true) {
            $this->saveInCache((int) $users->getId(), $users->getData());
        }

        return $users->getData();
    }

    public function delete(int $userId): void
    {
        $this->getUserFromDb($userId);
        $this->userRepository->deleteUserTasks($userId);
        $this->userRepository->delete($userId);
        if (self::isRedisEnabled() === true) {
            $this->deleteFromCache($userId);
        }
    }

    public function login(array $input): string
    {
        $data = json_decode((string) json_encode($input), false);
        if (! isset($data->email)) {
            throw new User('The field "email" is required.', 400);
        }
        if (! isset($data->password)) {
            throw new User('The field "password" is required.', 400);
        }
        $password = hash('sha512', $data->password);
        $user = $this->userRepository->loginUser($data->email, $password);
        $token = [
            'sub' => $user->getId(),
            'email' => $user->getEmail(),
            'name' => $user->getName(),
            'iat' => time(),
            'exp' => time() + (7 * 24 * 60 * 60),
        ];

        return JWT::encode($token, $_SERVER['SECRET_KEY']);
    }

    private function validateUserData(array $input): \App\Entity\User
    {
        $user = json_decode((string) json_encode($input), false);
        if (! isset($user->name)) {
            throw new User('The field "name" is required.', 400);
        }
        if (! isset($user->email)) {
            throw new User('The field "email" is required.', 400);
        }
        if (! isset($user->password)) {
            throw new User('The field "password" is required.', 400);
        }
        $myuser = new \App\Entity\User();
        $myuser->updateName(self::validateUserName($user->name));
        $myuser->updateEmail(self::validateEmail($user->email));
        $myuser->updatePassword(hash('sha512', $user->password));
        $this->userRepository->checkUserByEmail($user->email);

        return $myuser;
    }
}
