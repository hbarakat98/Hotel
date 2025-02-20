<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingRoomList;
use App\Models\Room;
use App\Models\RoomBookedDate;
use App\Models\RoomNumber;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use function date;
use function rand;
use function redirect;
use function strtotime;
use Stripe;

class BookingController extends Controller
{
    public function Checkout()
    {

        if (Session::has('book_date')){
            $book_data = Session::get('book_date');
            $room = Room::find($book_data['room_id']);

            $toDate =Carbon::parse($book_data['check_in']);
            $fromDate =Carbon::parse($book_data['check_out']);
            $nights = $toDate->diffInDays($fromDate);

            return view('frontend.checkout.checkout',compact('book_data','room','nights'));
        }else{
            $notification = array(
                'message' => 'Something went wrong!',
                'alert-type' => 'error',
            );

            return redirect('/')->with($notification);
        } //end else

    }//End Method

    public function BookingStore(Request $request)
    {
        $validateData = $request->validate([
            'check_in' => 'required',
            'check_out' => 'required',
            'person' => 'required',
            'number_of_rooms' => 'required',
        ]);

        if($request->available_room < $request->number_of_rooms){

            $notification = array(
                'message' => 'Something went wrong!',
                'alert-type' => 'error',
            );

            return redirect()->back()->with($notification);
        }

        Session::forget('book_date');

        $data = array();
        $data['number_of_rooms'] = $request->number_of_rooms;
        $data['available_room'] = $request->available_room;
        $data['person'] = $request->person;
        $data['check_in'] = date('Y-m-d',strtotime($request->check_in));
        $data['check_out'] = date('Y-m-d',strtotime($request->check_out));
        $data['room_id'] = $request->room_id;

        Session::put('book_date',$data);

        return redirect()->route('checkout');

    }//End Method

    public function CheckoutStore(Request $request)
    {
        //dd(env('STRIPE_SECRET'));
        $this->validate($request,[
            'name' => 'required',
            'email' => 'required',
            'country' => 'required',
            'phone' => 'required',
            'address' => 'required',
            'state' => 'required',
            'zip_code' => 'required',
            'payment_method' => 'required',
        ]);

        $book_data = Session::get('book_date');
        $toDate =Carbon::parse($book_data['check_in']);
        $fromDate =Carbon::parse($book_data['check_out']);
        $total_nights = $toDate->diffInDays($fromDate);

        $room = Room::find($book_data['room_id']);
        $subtotal = $room->price * $total_nights * $book_data['number_of_rooms'];
        $discount = $subtotal * ($room->discount/100);
        $total_price = $subtotal -$discount;
        $code = rand(000000000,999999999);

        if($request->payment_method == 'Stripe'){
            Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));
            $s_pay = Stripe\Charge::create([
                    'amount' => $total_price * 100,
                    'currency' => 'usd',
                    'source'=>$request->stripeToken,
                    'description' => "Payment For Booking. Booking No ".$code,
                ]);

            if($s_pay['status'] == 'succeeded'){
                $payment_status = 1;
                $transaction_id = $s_pay->id;
            }else{
                $notification = array(
                    'message' => 'Sorry Payment Failed',
                    'alert-type' => 'error',
                );
                return redirect('/')->with($notification);
            }

        }else{
            $payment_status = 0;
            $transaction_id = '';
        }



        $data = new Booking();
        $data->rooms_id = $room->id;
        $data->user_id = Auth::user()->id;
        $data->check_in = date('Y-m-d',strtotime($book_data['check_in']));
        $data->check_out = date('Y-m-d',strtotime($book_data['check_out']));
        $data->person = $book_data['person'];
        $data->number_of_rooms = $book_data['number_of_rooms'];
        $data->total_night = $total_nights;
        $data->actual_price = $room->price;
        $data->sub_total = $subtotal;
        $data->discount = $discount;
        $data->total_price = $total_price;
        $data->payment_method = $request->payment_method;
        $data->transaction_id = '';
        $data->payment_status = 0;

        $data->name = $request->name;
        $data->email = $request->email;
        $data->phone = $request->phone;
        $data->country = $request->country;
        $data->state = $request->state;
        $data->zip_code = $request->zip_code;
        $data->address = $request->address;

        $data->code = $code;
        $data->status = 0;
        $data->created_at = Carbon::now();
        $data->save();

        $sdate = date('Y-m-d',strtotime($book_data['check_in']));
        $edate = date('Y-m-d',strtotime($book_data['check_out']));
        $eldate = Carbon::create($edate)->subDay();
        $d_period = CarbonPeriod::create($sdate,$eldate);

        foreach ($d_period as $period){
            $booked_dates = new RoomBookedDate();
            $booked_dates->booking_id = $data->id;
            $booked_dates->room_id = $room->id;
            $booked_dates->book_date = date('Y-m-d',strtotime($period));
            $booked_dates->save();
        }

        Session::forget('book_date');

        $notification = array(
            'message' => 'Booking added Successfully',
            'alert-type' => 'success',
        );

        return redirect('/')->with($notification);



    }//End Method


    public function BookingList()
    {
        $allData = Booking::orderBy('id','desc')->get();
        return view('backend.booking.booking_list',compact('allData'));
    }// End Method

    public function EditBooking($id)
    {
        $editData = Booking::with('room')->find($id);
        return view('backend.booking.edit_booking',compact('editData'));
    }//End Method


    public function UpdateBookingStatus(Request $request, $id)
    {
        $booking = Booking::find($id);
        $booking->status = $request->status;
        $booking->payment_status = $request->payment_status;
        $booking->save();

        $notification = array(
            'message' => 'Booking updates Successfully',
            'alert-type' => 'success',
        );
        return redirect()->back()->with($notification);

    } //End Method

    public function UpdateBooking(Request $request, $id)
    {
        if ($request->available_room < $request->number_of_rooms){
            $notification = array(
                'message' => 'Something went wrong!',
                'alert-type' => 'error',
            );
            return redirect()->back()->with($notification);
        }

        $data = Booking::find($id);
        $data->number_of_rooms = $request->number_of_rooms;
        $data->check_in = date('Y-m-d',strtotime($request->check_in));
        $data->check_out = date('Y-m-d',strtotime($request->check_out));
        $data->save();

        RoomBookedDate::where('booking_id',$id)->delete();

        $sdate = date('Y-m-d',strtotime($request->check_in));
        $edate = date('Y-m-d',strtotime($request->check_out));
        $eldate = Carbon::create($edate)->subDay();
        $d_period = CarbonPeriod::create($sdate,$eldate);

        foreach ($d_period as $period){
            $booked_dates = new RoomBookedDate();
            $booked_dates->booking_id = $data->id;
            $booked_dates->room_id = $data->rooms_id;
            $booked_dates->book_date = date('Y-m-d',strtotime($period));
            $booked_dates->save();
        }

        $notification = array(
            'message' => 'Booking updated Successfully',
            'alert-type' => 'success',
        );
        return redirect()->back()->with($notification);

    } //End Method

    public function AssignRoom($booking_id)
    {
        $booking = Booking::find($booking_id);

        $booking_date_array = RoomBookedDate::where('booking_id',$booking_id)->pluck('book_date')->toArray();

        $check_date_booking_ids = RoomBookedDate::whereIn('book_date',$booking_date_array)->where('room_id',$booking->rooms_id)->distinct()->pluck('booking_id')->toArray();

        $bookings_ids =Booking::whereIn('id',$check_date_booking_ids)->pluck('id')->toArray();

        $assign_room_ids = BookingRoomList::whereIn('booking_id',$bookings_ids)->pluck('room_number_id')->toArray();

        $room_numbers = RoomNumber::where('rooms_id',$booking->rooms_id)->whereNotIn('id',$assign_room_ids)->where('status','Active')->get();

        return view('backend.booking.assign_room',compact('booking','room_numbers'));

    } //End Method


    public function AssignRoomStore($booking_id,$room_number_id)
    {

        $booking = Booking::find($booking_id);
        $check_data = BookingRoomList::where('booking_id',$booking_id)->count();

        if($check_data < $booking->number_of_rooms){
            $assign_data = new BookingRoomList();
            $assign_data->booking_id = $booking_id;
            $assign_data->room_id = $booking->rooms_id;
            $assign_data->room_number_id = $room_number_id;
            $assign_data->save();

            $notification = array(
                'message' => 'Room Assigned Successfully',
                'alert-type' => 'success',
            );
            return redirect()->back()->with($notification);
        }else{
            $notification = array(
                'message' => 'Room already Assigned',
                'alert-type' => 'error',
            );
            return redirect()->back()->with($notification);
        }
    } //End Method

    public function AssignRoomDelete($id)
    {
        $assign_room = BookingRoomList::find($id);
        $assign_room->delete();

        $notification = array(
            'message' => 'Assign Room Deleted Successfully',
            'alert-type' => 'success',
        );
        return redirect()->back()->with($notification);
    } //End Method

}
