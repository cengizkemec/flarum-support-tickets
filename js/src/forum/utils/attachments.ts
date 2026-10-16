import app from 'flarum/forum/app';

/** Maximum size accepted by the server (see UploadAttachmentController). */
export const MAX_ATTACHMENT_SIZE = 5 * 1024 * 1024;

const IMAGE_RE = /\.(png|jpe?g|gif|webp)$/i;

export interface UploadedAttachment {
  filename: string;
  originalName: string;
}

/**
 * Upload a single file to the support ticket attachment endpoint.
 *
 * The request intentionally uses FormData so the browser sets the correct
 * multipart boundary; `app.request` passes it through untouched.
 */
export function uploadAttachment(file: File): Promise<UploadedAttachment> {
  const formData = new FormData();
  formData.append('file', file);

  return app.request({
    method: 'POST',
    url: app.forum.attribute('apiUrl') + '/support/upload',
    body: formData,
  }) as Promise<UploadedAttachment>;
}

/** Absolute URL used to open or display a stored attachment. */
export function attachmentUrl(filename: string): string {
  return app.forum.attribute('apiUrl') + '/support/attachments/' + encodeURIComponent(filename);
}

/** Whether a stored attachment can be rendered as an inline image. */
export function isImageAttachment(filename?: string | null): boolean {
  return !!filename && IMAGE_RE.test(filename);
}

/**
 * Return the first image contained in a paste event's clipboard, or null when
 * the clipboard holds no image (so plain text pasting keeps working).
 */
export function pastedImage(event: any): File | null {
  const data = event && event.clipboardData;
  if (!data) return null;

  if (data.files && data.files.length) {
    for (let i = 0; i < data.files.length; i++) {
      const file = data.files[i];
      if (file && file.type && file.type.indexOf('image/') === 0) {
        return file;
      }
    }
  }

  const items = data.items;
  if (items) {
    for (let i = 0; i < items.length; i++) {
      const item = items[i];
      if (item.kind === 'file' && item.type && item.type.indexOf('image/') === 0) {
        const file = item.getAsFile();
        if (file) return file;
      }
    }
  }

  return null;
}

/** Validate a file client-side before uploading it. */
export function attachmentError(file: File): string | null {
  if (file.size > MAX_ATTACHMENT_SIZE) {
    return 'too_large';
  }

  return null;
}
