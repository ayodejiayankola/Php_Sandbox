  
<?php

namespace App\Http\Controllers;

use PDF;
use Auth;
use Hash;
use App\User;
use App\ScratchCard;

use Illuminate\Http\Request;

class ScratchCardController extends Controller
{
    public function __construct(){
        $this->middleware('checkRole:admin');
    }
    private function generateSerial(){
        return date('Ymdhis')*(rand(1,10) + rand(5,10));
    }
    private function getChunkPIN(){
        return rand(1000, 9999);
    }

    // generate  unique pin
    private function generatePIN(){
        $pin = '';
        $i = 0;
        while($i < 4){
            $pin .= $this->getChunkPIN();
            $i++;
        }

        // confirm if the pin doesn't exist
        if(ScratchCard::where('pin', $pin)->count() > 0){
            return $this->generatePIN();
        }
        return $pin;
    }


    public function index(){
        return view('card.index');
    }

    public function generateCard(Request $request){
        $this->validate($request,[
            'card_value' => ['required','numeric'],
            'quantity' => ['required','numeric'],
            'password' => ['required']
        ]);

        // confirm password
        if(Auth::user()->admin && Hash::check($request->password,Auth::user()->password)){
            $count = 0;
            while($count < $request->quantity){
                ScratchCard::create([
                    'pin' => $this->generatePIN(),
                    'serial' => $this->generateSerial(),
                    'value' => $request->card_value,
                    'generated_by' => Auth::id(),
                ]);
                $count++;
            }
            return redirect()->route('card.index')->with('success', "$count of $request->card_value scratch cards generated");
        }
        return redirect()->back()->with('error','Unauthorized, the password is incorrect');
    }

    public function revokeCard(Request $request){
        $card = ScratchCard::where('serial', $request->serial)->firstorfail();
        if($card->isRevoked()){
            return redirect()->back()->with('error', 'card already revoked since '.$card->revoked_at->format('d m, Y h:i:s a'));
        }
        $card->revoked_at = now();
        $card->save();
        return redirect()->back()->with('success', 'Card revoked');
    }

    public function restoreCard(Request $request){
        $card = ScratchCard::where('serial', $request->serial)->firstorfail();
        if(!$card->isRevoked()){
            return redirect()->back()->with('error', 'Card was not revoked');
        }
        $card->revoked_at = null;
        $card->save();
        return redirect()->back()->with('success', 'Card restored');
    }

    public function printCard(Request $request){
        $card = ScratchCard::where('serial', $request->serial)->firstorfail();
        if($card->isRevoked()){
            return redirect()->back()->with('error', 'Card can not be printed. Card revoked since '.$card->revoked_at->format('d m, Y h:i:s a'));
        }
        $card->printed_at = now();
        $card->save();
        $card_pdf = PDF::loadView('card.print',['card'=>$card]);
        return $card_pdf->stream($card->serial.'.pdf');
    }

    public function showCard($serial)
    {
        return view('card.show')->with('card', ScratchCard::where('serial', $serial)->first())
 []                    ->with('serial', $serial);
    }
    
    public function verifyCard(Request $request){
        $serial = request()->get('serial');
        if($serial == null){
           return redirect()->back()->with('error', 'No valid serial number');
        }
        $card = ScratchCard::where('serial', $serial)->first();

        return view('card.show')->with('card', $card)->with('serial', $serial);              
    }
}