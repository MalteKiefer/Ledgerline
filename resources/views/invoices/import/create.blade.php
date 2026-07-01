<x-layouts.app title="Import invoice">
    <x-finance-nav />
    <p class="text-sm text-gray-500"><a href="{{ route('finance.invoices.index') }}" class="hover:underline">Invoices</a></p>
    <h1 class="mt-1 text-2xl font-semibold text-gray-900">Import invoice</h1>
    <p class="mt-1 text-sm text-gray-600">Upload an existing invoice PDF. Its data is read for you to review before saving.</p>

    <form method="POST" action="{{ route('finance.invoices.import.parse') }}" enctype="multipart/form-data"
        class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        @csrf
        <label for="file" class="block text-sm font-medium text-gray-700">Invoice PDF</label>
        <input type="file" id="file" name="file" accept="application/pdf" required
            class="mt-2 text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-gray-800 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-gray-700">
        @error('file')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        <div class="mt-4">
            <button type="submit" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">Read &amp; review</button>
        </div>
    </form>
</x-layouts.app>
