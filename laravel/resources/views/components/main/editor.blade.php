@extends('layouts.app')

@section('content')
    <x-editor
        :version="$version"
        :comparison="$comparison"
        :xml-content="$xmlContent"
        :is-source="$isSource"
        :is-published="$isPublished"
        :can-edit="$canEdit"
        :images-data="$imagesData"
    />
@endsection
