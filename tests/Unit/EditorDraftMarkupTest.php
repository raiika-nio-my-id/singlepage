<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class EditorDraftMarkupTest extends TestCase
{
    public function test_edit_and_create_forms_include_autosave_hooks(): void
    {
        $edit = File::get(resource_path('views/admin/company-edit.blade.php'));
        $create = File::get(resource_path('views/admin/company-create.blade.php'));
        $layout = File::get(resource_path('views/layouts/admin.blade.php'));

        $this->assertStringContainsString('data-editor-form', $edit);
        $this->assertStringContainsString('data-unsaved-flag', $edit);
        $this->assertStringContainsString('Ctrl+S', $edit);
        $this->assertStringContainsString('id="name" type="text"', $create);
        $this->assertStringContainsString('id="subdomain" type="text"', $create);
        $this->assertStringContainsString('id="name" type="text"', $edit);
        $this->assertStringContainsString('id="subdomain" type="text"', $edit);
        $this->assertStringContainsString('data-unsaved-flag', $create);
        $this->assertStringContainsString('js/editor-draft.js', $layout);
        $this->assertStringContainsString('js/theme.js', $layout);
        $this->assertStringContainsString('partials.theme-toggle', $layout);
    }

    public function test_edit_form_keeps_aliases_above_snippet_and_preview(): void
    {
        $edit = File::get(resource_path('views/admin/company-edit.blade.php'));

        $aliasPos = strpos($edit, 'class="card alias-panel"');
        $splitPos = strpos($edit, 'class="editor-split"');
        $previewPos = strpos($edit, '<x-live-preview');

        $this->assertNotFalse($aliasPos);
        $this->assertNotFalse($splitPos);
        $this->assertNotFalse($previewPos);
        $this->assertLessThan($splitPos, $aliasPos, 'Alias panel should sit above the snippet | preview row.');
        $this->assertGreaterThan($splitPos, $previewPos, 'Live preview must remain inside editor-split with the snippet.');
    }
}
