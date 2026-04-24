<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\InteractsWithCatalogItems;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImageGallery\UpdateImageRequest;
use App\Http\Requests\ImageGallery\UploadImageRequest;
use App\Models\GalleryImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ImageGalleryController extends Controller
{
    use InteractsWithCatalogItems;

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('_page', 1));
        $perPage = max(1, min((int) $request->query('_per_page', 10), 100));

        $query = GalleryImage::query()
            ->active()
            ->with('equipmentProps:id');

        if ($request->filled('item_type')) {
            $query->where('item_type', strtoupper((string) $request->query('item_type')));
        }

        $images = $query->orderByDesc('id')->paginate($perPage, ['*'], 'page', $page);

        return $this->success([
            'items' => collect($images->items())
                ->map(fn (GalleryImage $image) => $this->transformImage($image))
                ->values()
                ->all(),
            'pagination' => [
                'current_page' => $images->currentPage(),
                'per_page' => $images->perPage(),
                'total' => $images->total(),
                'last_page' => $images->lastPage(),
            ],
        ], 'Lấy danh sách ảnh thành công!');
    }

    public function upload(UploadImageRequest $request): JsonResponse
    {
        $itemType = $request->validated('item_type');
        $images = collect($request->file('file'))
            ->map(function (UploadedFile $file) use ($itemType): array {
                $path = $file->store('images-gallery/'.strtolower((string) $itemType), 'public');

                $image = GalleryImage::query()->create([
                    'item_type' => $itemType,
                    'disk' => 'public',
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                    'is_active' => true,
                ]);

                return $this->transformImage($image);
            })
            ->values()
            ->all();

        return $this->success($images, 'Tải ảnh lên thành công!', Response::HTTP_CREATED);
    }

    public function show(int $id): JsonResponse
    {
        $image = GalleryImage::query()
            ->active()
            ->with('equipmentProps:id')
            ->findOrFail($id);

        return $this->success($this->transformImage($image), 'Lấy chi tiết ảnh thành công!');
    }

    public function update(UpdateImageRequest $request, int $id): JsonResponse
    {
        $image = GalleryImage::query()->findOrFail($id);
        $image->update($request->validated());

        return $this->success($this->transformImage($image->fresh(['equipmentProps:id'])), 'Cập nhật ảnh thành công!');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $image = GalleryImage::query()->findOrFail($id);

        if ($this->shouldDeletePermanently($request)) {
            Storage::disk($image->disk)->delete($image->path);
            $image->delete();
        } else {
            $image->update(['is_active' => false]);
        }

        return $this->success(null, 'Xóa ảnh thành công!');
    }

    private function transformImage(GalleryImage $image): array
    {
        $image->loadMissing('equipmentProps:id');

        return [
            ...$this->transformGalleryImage($image),
            'equipment_prop_ids' => $image->equipmentProps->pluck('id')->values()->all(),
        ];
    }

    private function shouldDeletePermanently(Request $request): bool
    {
        if (
            ($request->has('permanently') && in_array($request->query('permanently'), [null, ''], true))
            || ($request->has('permanantly') && in_array($request->query('permanantly'), [null, ''], true))
        ) {
            return true;
        }

        return $request->boolean('permanently') || $request->boolean('permanantly');
    }
}
