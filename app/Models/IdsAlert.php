<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Modelo para alertas IDS.
 *
 * Representa alertas generadas por sensores de detección de intrusos.
 * Se mantiene enfocada a integración con API REST y consultas en tiempo real.
 */
class IdsAlert extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'ids_alerts';

    protected $fillable = [
        'created_at',
        'severity',
        'alert_type',
        'source_ip',
        'destination_ip',
        'signature',
        'status',
        'sensor_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public const SEVERITIES = ['critical', 'high', 'medium', 'low'];
    public const STATUSES = ['Nueva', 'En Investigación', 'Mitigada'];

    /**
     * Sensor que envio la alerta (null si es anterior al registro de sensores).
     */
    public function sensor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(IdsSensor::class, 'sensor_id');
    }
}
