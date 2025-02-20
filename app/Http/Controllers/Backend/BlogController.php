<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Intervention\Image\Facades\Image;


class BlogController extends Controller
{
    //

    public function BlogCategory()
    {
        $category = BlogCategory::latest()->get();
        return view('backend.category.blog_category', compact('category'));
    } //End Method

    public function StoreBlogCategory(Request $request)
    {
        BlogCategory::insert([
            'category_name'=>$request->category_name,
            'category_slug'=>strtolower(str_replace(' ','-',$request->category_name)),
        ]);
        $notification = array(
            'message' => 'Blog Category Added Successfully',
            'alert-type' => 'success',
        );

        return redirect()->back()->with($notification);

    } //End Method

    public function EditBlogCategory($id)
    {
            $categories = BlogCategory::find($id);
            return response()->json($categories);
    } //End Method

    public function UpdateBlogCategory(Request $request)
    {
        $cat_id = $request->cat_id;
        BlogCategory::find($cat_id)->update([
            'category_name'=>$request->category_name,
            'category_slug'=>strtolower(str_replace(' ','-',$request->category_name)),
        ]);

        $notification = array(
            'message' => 'Blog Category Updated Successfully',
            'alert-type' => 'success',
        );

        return redirect()->back()->with($notification);
    }//End Method

    public function DeleteBlogCategory($id)
    {
        $item = BlogCategory::findOrFail($id);

        BlogCategory::findOrFail($id)->delete();

        $notification = array(
            'message' => 'Blog Category Deleted Successfully',
            'alert-type' => 'success',
        );

        return redirect()->back()->with($notification);
    } //End Function

    public function AllBlogPost()
    {
        $post = BlogPost::latest()->get();
        return view('backend.post.all_post', compact('post'));
    } // End Method

    public function AddBlogPost()
    {
        $blogcat = BlogCategory::latest()->get();
        return view('backend.post.add_post', compact('blogcat'));
    } //End Method

    public function StoreBlogPost(Request $request)
    {
        $image =$request->file('post_image');
        $name_gen=hexdec(uniqid()).'.'.$image->getClientOriginalExtension();
        Image::make($image)->resize(550,370)->save('upload/post/'.$name_gen);
        $save_url ='upload/post/'.$name_gen;

        BlogPost::insert([
            'blogcat_id' => $request->blogcat_id,
            'user_id' => Auth::user()->id,
            'post_title' => $request->post_title,
            'post_slug'=>strtolower(str_replace(' ','-',$request->post_slug)),
            'post_image' => $save_url,
            'short_descp' => $request->short_descp,
            'long_descp' => $request->long_descp,
            'created_at' => Carbon::now(),

        ]);

        $notification = array(
            'message' => 'Blog Post Inserted Successfully',
            'alert-type' => 'success',
        );

        return redirect()->route('all.blog.post')->with($notification);
    } //End Function
}
