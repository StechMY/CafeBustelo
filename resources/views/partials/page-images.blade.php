@php
    $pageImages = \App\Models\PageImage::query()->visibleForPage($page)->get();
@endphp

@if ($pageImages->isNotEmpty())
    <div class="mt-4 grid gap-3 sm:mt-5 sm:gap-4">
        @foreach ($pageImages as $pageImage)
            <img
                 src="{{ $pageImage->image_url }}"
                 alt="{{ $pageImage->title }}"
                 class="w-full rounded-[1.35rem] object-cover shadow-xl ring-1 ring-black/5 sm:rounded-[2rem]"
                 loading="lazy">
        @endforeach
    </div>
@endif
