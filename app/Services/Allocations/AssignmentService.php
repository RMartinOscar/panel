<?php

namespace App\Services\Allocations;

use App\Models\Allocation;
use IPTools\Network;
use App\Models\Node;
use App\Models\Server;
use Illuminate\Database\ConnectionInterface;
use App\Exceptions\DisplayException;
use App\Exceptions\Service\Allocation\CidrOutOfRangeException;
use App\Exceptions\Service\Allocation\PortOutOfRangeException;
use App\Exceptions\Service\Allocation\InvalidPortMappingException;
use App\Exceptions\Service\Allocation\TooManyPortsInRangeException;

class AssignmentService
{
    public const CIDR_MAX_BITS = 25;

    public const CIDR_MIN_BITS = 32;

    public const PORT_FLOOR = 1024;

    public const PORT_CEIL = 65535;

    public const PORT_RANGE_LIMIT = 1000;

    public const PORT_RANGE_REGEX = '/^(\d{4,5})-(\d{4,5})$/';

    /**
     * AssignmentService constructor.
     */
    public function __construct(protected ConnectionInterface $connection)
    {
    }

    /**
     * Insert allocations into the database and link them to a specific node.
     *
     * @throws \App\Exceptions\DisplayException
     * @throws \App\Exceptions\Service\Allocation\CidrOutOfRangeException
     * @throws \App\Exceptions\Service\Allocation\InvalidPortMappingException
     * @throws \App\Exceptions\Service\Allocation\PortOutOfRangeException
     * @throws \App\Exceptions\Service\Allocation\TooManyPortsInRangeException
     */
    public function handle(Node $node, array $data, ?Server $server = null): array
    {
        $explode = explode('/', $data['allocation_ip']);
        if (count($explode) !== 1 && (!ctype_digit($explode[1]) || ($explode[1] > self::CIDR_MIN_BITS || $explode[1] < self::CIDR_MAX_BITS))) {
            throw new CidrOutOfRangeException();
        }

        try {
            // TODO: how should we approach supporting IPv6 with this?
            // gethostbyname only supports IPv4, but the alternative (dns_get_record) returns
            // an array of records, which is not ideal for this use case, we need a SINGLE
            // IP to use, not multiple.
            $underlying = gethostbyname($data['allocation_ip']);
            $ip = Network::parse($underlying)
                ->getIp();
        } catch (\Exception $exception) {
            throw new DisplayException("Could not parse provided allocation IP address ({$data['allocation_ip']}): {$exception->getMessage()}", $exception);
        }

        $this->connection->beginTransaction();

        $ids = [];
        $failed = collect();
        $allocation_ports = $data['allocation_ports'];

        $ports = collect($allocation_ports)
            ->flatMap(function ($port) {
                if (!is_digit($port) && !preg_match(self::PORT_RANGE_REGEX, $port)) {
                    throw new InvalidPortMappingException($port);
                }

                if (is_numeric($port)) {
                    return [(int) $port];
                }

                if (str_contains($port, '-')) {
                    [$start, $end] = explode('-', $port);
                    if (is_numeric($start) && is_numeric($end)) {
                        $start = max((int) $start, 1024);
                        $end = min((int) $end, 65535);

                        return range($start, $end);
                    }
                }

                if ((int) $port < self::PORT_FLOOR || (int) $port > self::PORT_CEIL) {
                    throw new PortOutOfRangeException();
                }

                return [];
            })
            ->unique()
            ->sort()
            ->filter(fn ($port) => $port > 1024 && $port < 65535)
            ->values();

        $insertData = $ports->map(function (int $port) use ($node, $ip, $data, $server) {
            return [
                'node_id' => $node->id,
                'ip' => $ip->__toString(),
                'port' => $port,
                'ip_alias' => array_get($data, 'allocation_alias'),
                'server_id' => $server->id ?? null,
            ];
        });

        if ($ports->count() > self::PORT_RANGE_LIMIT) {
            throw new TooManyPortsInRangeException();
        }

        foreach ($insertData as $insert) {
            try {
                $allocation = Allocation::query()->create($insert);
                $ids[] = $allocation->id;
            } catch (\Exception) {
                $failed->push($insert['port']);
            }
        }

        if ($failed->isNotEmpty()) {
            throw new DisplayException("Could not add provided allocation IP address ({$data['allocation_ip']}) with Ports ({$failed->join(', ')}) already exist.");
        }

        $this->connection->commit();

        return $ids;
    }
}
