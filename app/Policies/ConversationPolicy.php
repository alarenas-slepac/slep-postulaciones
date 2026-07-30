<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;
use App\Support\Messaging\FuncionarioAcDirectory;

class ConversationPolicy
{
    public function start(User $user): bool
    {
        return FuncionarioAcDirectory::canUseDirectory($user)
            || FuncionarioAcDirectory::canStartGeneralConversation($user);
    }

    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->participants()->where('users.id', $user->id)->exists();
    }

    public function send(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }

    public function poll(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }
}
