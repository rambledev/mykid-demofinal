<?php
/**
 * CCTV mock data: 1 school × 5 TP-Link Tapo C200C = 5 cameras.
 * Every camera row carries school_id, so schools never see each other's cameras.
 * All cameras start as stream_type "mock" (see docs/cctv-architecture.md to go live).
 */
declare(strict_types=1);

function pkg_cctv_cameras(): array
{
    return [
        1 => cctv_school_cameras(['CAM-03' => 'offline']), // ศูนย์พัฒนาเด็กเล็กบ้านโพนสูง
    ];
}
