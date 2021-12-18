<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Models\Variant;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Http\Response|\Illuminate\View\View
     */
    public function index(Request $request)
    {
        $products = Product::query();
        // $products->

        if($request->has('title') && ($request->title != ''))
        {
            $products->where('title', 'LIKE', '%'.$request->title.'%');
        }

        if($request->has('variant') && ($request->variant != ''))
        {
            $variant = $request->variant;
            $products->whereHas('productVariants', function($q) use ($variant) {
                $q->where('variant', $variant);
            });
        }


        if($request->has('price_from') && ($request->price_from != ''))
        {
            $price = $request->price_from;
            $products->with(['variantPrices' => function($q) use ($price) {
                return $q->where('price', '>=', $price);
            }])->whereHas('variantPrices', function($q) use ($price) {
                $q->where('price', '>=', $price);
            });
        }

        if($request->has('price_to') && ($request->price_to != ''))
        {
            $price = $request->price_to;
            $products->with(['variantPrices' => function($q) use ($price) {
                return $q->where('price', '<=', $price);
            }])->whereHas('variantPrices', function($q) use ($price) {
                $q->where('price', '<=', $price);
            });
        }

        if($request->has('date') && ($request->date != ''))
        {
            $products->whereDate('created_at', $request->date);
        }


        $variants = Variant::all();

        $products = $products->with('variantPrices.variantOne', 'variantPrices.variantTwo', 'variantPrices.variantThree')->paginate(5);
        return view('products.index', compact('products', 'variants'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Http\Response|\Illuminate\View\View
     */
    public function create()
    {
        $variants = Variant::all();
        return view('products.create', compact('variants'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {

        $product = Product::create([
            'title' => $request->title,
            'sku' => $request->sku,
            'description' => $request->description
        ]);

        foreach($request->product_variant as $variants)
        {
            foreach($variants['tags'] as $variant)
            {
                ProductVariant::create([
                    'variant_id' => $variants['option'],
                    'product_id' => $product->id,
                    'variant' => $variant
                ]);
            }
        }

        foreach($request->product_variant_prices as $variant_price)
        {
            $variation = [];
            $tags  = explode("/", $variant_price['title']);

            for($i=0; $i<count($tags)-1; $i++)
            {
                $product_variant = ProductVariant::where('variant', $tags[$i])->where('product_id', $product->id)->first();



                if($i==0)
                    $variation['product_variant_one'] = $product_variant->id;
                else if($i==1)
                    $variation['product_variant_two'] = $product_variant->id;
                else if($i==2)
                    $variation['product_variant_three'] = $product_variant->id;
            }

            $variation['price'] = $variant_price['price'];
            $variation['stock'] = $variant_price['stock'];
            $variation['product_id'] = $product->id;

            ProductVariantPrice::create($variation);
        }

        $s = 0;
        foreach($request->product_image as $image)
        {
            if($s == 0)
            {
                ProductImage::create([
                    'product_id' => $product->id,
                    'file_path' => $image,
                    'thumbnail' => true
                ]);
            }else{
                ProductImage::create([
                    'product_id' => $product->id,
                    'file_path' => $image,
                    'thumbnail' => false
                ]);
            }

            $s++;
        }

        // return $request->file('product_image');
        return response()->json([
            'message' => 'Success'
        ], 200);
    }

    public function fileUpload(Request $request)
    {
        if($request->hasFile('file'))
        {
            $file = $request->file('file');

            $name = time().'_'.$file->getClientOriginalName();

            $path = public_path('upload/images');

            $file->move($path, $name);

            return url('upload/images').'/'.$name;

        }
    }


    /**
     * Display the specified resource.
     *
     * @param \App\Models\Product $product
     * @return \Illuminate\Http\Response
     */
    public function show($product)
    {

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param \App\Models\Product $product
     * @return \Illuminate\Http\Response
     */
    public function edit(Product $product)
    {
        $variants = Variant::all();
        $productVariationPrice = ProductVariantPrice::with('variantOne', 'variantTwo', 'variantThree')->where('product_id', $product->id)->get();
        // return $productVariationPrice;
        return view('products.edit', compact('variants', 'product', 'productVariationPrice'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Product $product
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Product $product)
    {
        $product->update([
            'title' => $request->title,
            'sku' => $request->sku,
            'description' => $request->description
        ]);

        if(count($request->product_image) > 0)
        {
            ProductImage::where('product_id', $product->id)->delete();

            $s = 0;
            foreach($request->product_image as $image)
            {
                if($s == 0)
                {
                    ProductImage::create([
                        'product_id' => $product->id,
                        'file_path' => $image,
                        'thumbnail' => true
                    ]);
                }else{
                    ProductImage::create([
                        'product_id' => $product->id,
                        'file_path' => $image,
                        'thumbnail' => false
                    ]);
                }

                $s++;
            }
        }

        foreach($request->product_variant_prices as $variant)
        {
            ProductVariantPrice::where('id', $variant['id'])->update([
                'price' => $variant['price'],
                'stock' => $variant['stock']
            ]);
        }

        return response()->json([
            'message' => 'Success'
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Models\Product $product
     * @return \Illuminate\Http\Response
     */
    public function destroy(Product $product)
    {
        //
    }
}
