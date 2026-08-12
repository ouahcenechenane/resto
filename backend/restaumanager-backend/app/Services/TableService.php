<?php
namespace App\Services;

use App\Events\TableCreated;
use App\Events\TableStatusChanged;
use App\Models\RestaurantTable;

class TableService
{
    public function create(array $data): RestaurantTable
    {
        $exists = RestaurantTable::where('section_id', $data['section_id'])
            ->where('number', $data['number'])->exists();
        if ($exists) {
            throw new \Exception('Une table avec ce numéro existe déjà dans cette section.');
        }

        $table = RestaurantTable::create([
            'section_id' => $data['section_id'],
            'number'     => $data['number'],
            'capacity'   => $data['capacity'],
            'status'     => 'available',
        ]);

        TableCreated::dispatch($table->load('section'));
        return $table;
    }

    public function updateStatus(RestaurantTable $table, string $newStatus, ?int $orderId = null): RestaurantTable
    {
        $allowed = ['available', 'occupied', 'reserved', 'closed'];
        if (!in_array($newStatus, $allowed)) {
            throw new \Exception('Statut invalide : ' . $newStatus);
        }

        $oldStatus = $table->status;
        if ($oldStatus === $newStatus) return $table;

        $table->update(['status' => $newStatus]);
        TableStatusChanged::dispatch($table->fresh()->load('section'), $oldStatus, $newStatus, $orderId);
        return $table->fresh();
    }

    public function update(RestaurantTable $table, array $data): RestaurantTable
    {
        if (isset($data['number']) && $data['number'] != $table->number) {
            $sectionId = $data['section_id'] ?? $table->section_id;
            $exists = RestaurantTable::where('section_id', $sectionId)
                ->where('number', $data['number'])
                ->where('id', '!=', $table->id)->exists();
            if ($exists) {
                throw new \Exception('Une table avec ce numéro existe déjà dans cette section.');
            }
        }

        $table->update(array_filter([
            'number'     => $data['number']     ?? null,
            'capacity'   => $data['capacity']   ?? null,
            'section_id' => $data['section_id'] ?? null,
        ], fn($v) => !is_null($v)));

        return $table->fresh()->load('section');
    }

    public function delete(RestaurantTable $table): void
    {
        if ($table->status === 'occupied') {
            throw new \Exception('Impossible de supprimer une table occupée.');
        }
        if ($table->orders()->whereIn('status', ['open', 'validated', 'billed'])->exists()) {
            throw new \Exception('Cette table a des commandes en cours.');
        }
        $table->delete();
    }
}
