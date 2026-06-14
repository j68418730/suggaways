<?php

namespace Middleware;

class GuestMiddleware
{
    public function handle(): void
    {
        if (!empty($_SESSION['user_id'])) {
            redirect('/?page=account');
        }
    }
}
