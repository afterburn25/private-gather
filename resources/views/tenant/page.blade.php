@extends('layouts.tenant-site')
@section('title',$page->seo_title?:$page->title.' — '.$tenant->name)
@section('meta_description',$page->seo_description?:'')
@section('content')@include('tenant._sections',['page'=>$page,'tenant'=>$tenant,'events'=>$events])@endsection
