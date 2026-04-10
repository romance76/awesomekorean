// 이미지를 canvas로 리사이즈 + 품질 압축
// 10MB 폰 사진 → 보통 200~600KB
export async function compressImage(file, { maxDim = 1600, quality = 0.8, mimeType = 'image/jpeg' } = {}) {
  if (!file || !file.type || !file.type.startsWith('image/')) return file
  // GIF 애니메이션은 압축하면 깨지니까 그대로
  if (file.type === 'image/gif') return file

  return new Promise((resolve, reject) => {
    const img = new Image()
    const url = URL.createObjectURL(file)
    img.onload = () => {
      URL.revokeObjectURL(url)
      let { width, height } = img
      // 긴 변 기준 최대 maxDim 으로 축소
      if (width > maxDim || height > maxDim) {
        if (width > height) {
          height = Math.round(height * (maxDim / width))
          width = maxDim
        } else {
          width = Math.round(width * (maxDim / height))
          height = maxDim
        }
      }
      const canvas = document.createElement('canvas')
      canvas.width = width
      canvas.height = height
      const ctx = canvas.getContext('2d')
      ctx.drawImage(img, 0, 0, width, height)
      canvas.toBlob(
        (blob) => {
          if (!blob) return resolve(file) // 실패 시 원본
          // 원본보다 크면 원본 사용
          if (blob.size >= file.size) return resolve(file)
          // Blob → File 로 wrap (이름 유지, .jpg 로 교체)
          const newName = file.name.replace(/\.[^.]+$/, '') + '.jpg'
          const compressed = new File([blob], newName, { type: mimeType, lastModified: Date.now() })
          resolve(compressed)
        },
        mimeType,
        quality
      )
    }
    img.onerror = () => {
      URL.revokeObjectURL(url)
      resolve(file) // 로드 실패 시 원본
    }
    img.src = url
  })
}

// 허용된 아카이브 확장자
const ARCHIVE_EXTS = ['.zip', '.rar', '.7z', '.tar', '.tar.gz', '.tgz', '.gz']
const ARCHIVE_MIMES = [
  'application/zip',
  'application/x-zip-compressed',
  'application/x-rar-compressed',
  'application/vnd.rar',
  'application/x-7z-compressed',
  'application/x-tar',
  'application/gzip',
  'application/x-gzip',
]

export function isImage(file) {
  return file?.type?.startsWith('image/')
}

export function isArchive(file) {
  if (!file) return false
  if (ARCHIVE_MIMES.includes(file.type)) return true
  const name = (file.name || '').toLowerCase()
  return ARCHIVE_EXTS.some(ext => name.endsWith(ext))
}

export function fileTypeLabel(file) {
  if (isImage(file)) return '이미지'
  if (isArchive(file)) return '압축파일'
  return '지원불가'
}
