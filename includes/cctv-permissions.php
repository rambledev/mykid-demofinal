<?php
/**
 * CCTV default access policy → seeded into the cameraPermissions table.
 *
 * Classroom cameras: teacher + parent of THAT classroom only.
 * Common-area cameras: only the area types listed here (camera "scene"), per role.
 * Parents do NOT get every camera automatically — the entrance camera is staff-only by default.
 * Admins can change these flags per camera in admin/cctv.php (Admin / Super Admin only).
 */
declare(strict_types=1);

return [
    'teacher' => ['ownClassroom' => true, 'commonAreas' => ['playground', 'gate']],
    'parent'  => ['ownClassroom' => true, 'commonAreas' => ['playground']],
];
