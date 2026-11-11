<?php

namespace App\Http\Controllers\Ai;

use App\Exceptions\Ai\AiException;
use App\Http\Controllers\Controller;
use App\Http\Middleware\Ai\AiResponseHeaders;
use App\Http\Requests\Ai\ScanRequest;
use App\Services\Ai\Scan\ImagePreparer;
use App\Services\Ai\Scan\LabelExtractor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * POST /ai/v1/scan. Runs only after auth, permission, CSRF and quota middleware.
 * Stateless: the photo and the result live in memory for this request only.
 */
class ScanController extends Controller
{
    public function __invoke(ScanRequest $request, ImagePreparer $preparer, LabelExtractor $extractor): JsonResponse
    {
        $file = $request->upload();
        $requestId = AiResponseHeaders::requestId($request);
        $start = hrtime(true);

        try {
            $payload = $extractor->extract($preparer->prepare($file), $requestId);
        } catch (AiException $e) {
            $this->log($requestId, $e->errorCode, $start);

            throw $e;
        } catch (Throwable) {
            // Drop the original: its message or trace could quote label content.
            $this->log($requestId, 'INTERNAL', $start);

            throw AiException::for('INTERNAL');
        } finally {
            $this->discard($file);
        }

        $this->log($requestId, 'ok', $start);

        return response()->json($payload);
    }

    /** Remove the PHP upload temp file now, including on error paths. */
    private function discard(UploadedFile $file): void
    {
        $path = $file->getPathname();
        if ($path !== '' && is_file($path)) {
            @unlink($path);
        }
    }

    /** Allowlisted metadata only: no image, OCR text or values. */
    private function log(string $requestId, string $status, int $start): void
    {
        Log::info('ai.scan', [
            'request_id' => $requestId,
            'feature' => 'scan',
            'status' => $status,
            'latency_ms' => (int) round((hrtime(true) - $start) / 1_000_000),
        ]);
    }
}
