<?php
/**
 * Image compression helper used when saving user-uploaded documents.
 * Users may upload up to 20 MB, but images are resized/recompressed
 * before being written to disk so actual storage stays small. PDFs are
 * stored as-is (shared hosting has no safe way to recompress a PDF
 * without a server-side tool like Ghostscript), just under the same
 * raised 20 MB cap.
 */
function compress_and_store_image(string $srcPath, string $mime, string $destPath, int $maxDim = 1600, int $jpegQuality = 72): bool {
    if (!function_exists('imagecreatetruecolor')) {
        // GD not available for some reason — fall back to storing the original file untouched.
        return copy($srcPath, $destPath);
    }
    $src = null;
    if ($mime === 'image/jpeg') { $src = @imagecreatefromjpeg($srcPath); }
    elseif ($mime === 'image/png') { $src = @imagecreatefrompng($srcPath); }
    if (!$src) { return copy($srcPath, $destPath); }

    $w = imagesx($src); $h = imagesy($src);
    if ($w <= 0 || $h <= 0) { imagedestroy($src); return copy($srcPath, $destPath); }

    $scale = min(1, $maxDim / max($w, $h));
    $newW = max(1, (int)round($w * $scale));
    $newH = max(1, (int)round($h * $scale));

    $resized = imagecreatetruecolor($newW, $newH);
    if ($mime === 'image/png') {
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
        imagefilledrectangle($resized, 0, 0, $newW, $newH, $transparent);
    }
    imagecopyresampled($resized, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);
    imagedestroy($src);

    $ok = ($mime === 'image/png')
        ? imagepng($resized, $destPath, 6)
        : imagejpeg($resized, $destPath, $jpegQuality);
    imagedestroy($resized);

    if (!$ok || !is_file($destPath) || filesize($destPath) === 0) {
        return copy($srcPath, $destPath);
    }
    return true;
}
