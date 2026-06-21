@php($status = \App\Support\HttpStatusCatalog::find(200))
@extends('errors.layout')

@section('title', $status['title'])
@section('eyebrow', $status['category'])
@section('code', (string) $status['code'])
@section('message', $status['title'])
@section('description', $status['message'])
@section('status_text', $status['text'])
@section('recommendation', $status['recommendation'])
@section('actions')
    <a class="button button-primary" href="{{ url('/') }}">Ana Sayfa</a>
    <a class="button button-secondary" href="{{ url('/products') }}">Ürünleri İncele</a>
@endsection
