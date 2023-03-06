<?php

declare(strict_types=1);

namespace Dvpost\TraceMonitor\Serialize;

final class MessageSerializer
{
    public function serialize(array $data): string
    {
        return json_encode($data);
    }
}
