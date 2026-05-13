<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\HandlesCatalogItems;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImageGallery\UpdateImageRequest;
use App\Http\Requests\ImageGallery\UploadImageRequest;
use App\Models\GalleryImage;
use App\Models\ItemCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ImageGalleryController extends Controller
{
    use HandlesCatalogItems;

    public function index(Request $request): JsonResponse
    {
        $query = GalleryImage::query()
            ->active()
            ->with(['category', 'equipmentProps:id']);

        $categoryId = $request->query('category_id:eq', $request->query('category_id'));

        if ($categoryId !== null && $categoryId !== '') {
            $query->where('category_id', $categoryId);
        }

        if ($request->has('_page') || $request->has('_per_page')) {
            $page = max(1, (int) $request->query('_page', 1));
            $perPage = max(1, min((int) $request->query('_per_page', 10), 100));
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
            ], 'Lay danh sach anh thanh cong!');
        }

        $images = $query->orderByDesc('id')
            ->get()
            ->map(fn (GalleryImage $image) => $this->transformImage($image))
            ->all();

        return $this->success($images, 'Lay danh sach anh thanh cong!');
    }

    public function upload(UploadImageRequest $request): JsonResponse
    {
        $category = ItemCategory::query()->findOrFail($request->validated('category_id'));

        $images = collect($request->file('files'))
            ->map(function (UploadedFile $file) use ($category): array {
                $fileName = Str::uuid().'.'.$file->getClientOriginalExtension();
                $path = $file->storeAs('images-gallery/'.$category->id, $fileName, 'public');

                $image = GalleryImage::query()->create([
                    'category_id' => $category->id,
                    'file_name' => basename($path),
                    'mime_type' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                    'dest' => Storage::disk('public')->url($path),
                    'created_by' => auth('api')->id(),
                    'is_active' => true,
                ]);

                return $this->transformImage($image);
            })
            ->values()
            ->all();

        return $this->success($images, 'Tai anh len thanh cong!', Response::HTTP_CREATED);
    }

    public function show(int $id): JsonResponse
    {
        $image = GalleryImage::query()
            ->active()
            ->with(['category', 'equipmentProps:id'])
            ->findOrFail($id);

        return $this->success($this->transformImage($image), 'Lay chi tiet anh thanh cong!');
    }

    public function update(UpdateImageRequest $request, int $id): JsonResponse
    {
        $image = GalleryImage::query()->findOrFail($id);
        $image->update($request->validated());

        return $this->success($this->transformImage($image->fresh(['category', 'equipmentProps:id'])), 'Cap nhat anh thanh cong!');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $image = GalleryImage::query()->findOrFail($id);

        if ($this->shouldDeletePermanently($request)) {
            $this->deleteStoredFile($image);
            $image->delete();
        } else {
            $image->update(['is_active' => false]);
        }

        return $this->success(null, 'Xoa anh thanh cong!');
    }

    private function transformImage(GalleryImage $image): array
    {
        $image->loadMissing('equipmentProps:id');

        return [
            ...$this->transformGalleryImage($image),
            'equipment_prop_ids' => $image->equipmentProps->pluck('id')->values()->all(),
        ];
    }

    private function deleteStoredFile(GalleryImage $image): void
    {
        $dest = (string) $image->dest;
        $path = str_starts_with($dest, '/storage/')
            ? substr($dest, strlen('/storage/'))
            : ltrim($dest, '/');

        if ($path !== '') {
            Storage::disk('public')->delete($path);
        }
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
