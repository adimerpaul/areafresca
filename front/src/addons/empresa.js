export function companyData () {
  try {
    return JSON.parse(localStorage.getItem('empresaAreaFresca') || '{}')
  } catch {
    return {}
  }
}
