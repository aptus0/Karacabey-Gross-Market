@extends('errors.layout')

@section('title', 'Oturum Süresi Doldu')
@section('eyebrow', '419 Oturum Yenile')
@section('code', '419')
@section('message', 'Güvenlik oturumunuz yenilenmeli')
@section('description', 'Form güvenlik anahtarı veya oturum süresi geçerliliğini kaybetti. Bu genellikle sayfa uzun süre açık kaldığında veya güvenlik doğrulaması yenilendiğinde görülür.')
@section('status_text', 'Oturum anahtarı süresi doldu')
@section('recommendation', 'Sayfayı yenileyin ve işlemi tekrar gönderin. Gerekirse tekrar giriş yapın.')
@section('actions')
    <a class="button button-primary" href="{{ url()->current() }}">Sayfayı Yeniden Aç</a>
    <a class="button button-secondary" href="{{ route('admin.login') }}">Yeniden Giriş Yap</a>
@endsection
