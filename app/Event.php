<?php
namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use DateTime, DateTimeZone;

class Event extends Model
{
    use SoftDeletes;

    public static function slug_from_name($name) {
        return preg_replace('/--+/', '-', preg_replace('/[^a-z0-9]+/', '-', strtolower($name)));
    }

    public static function find_from_url($url) {
        // /{year}/{month}/{slug}-{key}
        if(preg_match('~/[0-9]{4}/[0-9]{2}/[0-9a-zA-Z\-]+-([0-9a-zA-Z]+)$~', $url, $match)) {
            return Event::where('key', $match[1])->first();
        } else {
            return null;
        }
    }

    public function responses() {
        return $this->hasMany('\App\Response');
    }

    public function rsvp_for_user(User $user) {
        return $this->responses()->where([
            'type' => 'rsvp',
            'rsvp_user_id' => $user->id
        ])->first();
    }

    public function rsvp_string_for_user(User $user) {
        $rsvp = $this->rsvp_for_user($user);
        return $rsvp ? $rsvp->rsvp : null;
    }

    public function tags() {
        return $this->belongsToMany('\App\Tag');
    }

    public function tags_string() {
        $tags = [];
        foreach($this->tags as $t)
            $tags[] = $t->tag;
        return implode(' ', $tags);
    }

    public function has_rsvps() {
        return $this->responses->where('type', 'rsvp')->count();
    }

    public function has_photos() {
        return $this->responses->where('type', 'photo')->count();
    }

    public function has_blog_posts() {
        return $this->responses->where('type', 'blog_post')->count();
    }

    public function has_comments() {
        return $this->responses->where('type', 'comment')->count();
    }

    public function rsvps() {
        return $this->hasMany('\App\Response')->where('type', 'rsvp');
    }

    public function photos() {
        return $this->hasMany('\App\Response')->where('type', 'photo');
    }

    public function blog_posts() {
        return $this->hasMany('\App\Response')->where('type', 'blog_post');
    }

    public function comments() {
        return $this->hasMany('\App\Response')->where('type', 'comment');
    }

    public function permalink() {
        $date = new DateTime($this->start_date);
        return '/' . $date->format('Y') . '/' . $date->format('m') . '/' . ($this->slug ? $this->slug.'-' : '') . $this->key;
    }

    public function absolute_permalink() {
        return env('APP_URL').$this->permalink();
    }

    public function is_multiday() {
        return $this->end_date && $this->end_date != $this->start_date;
    }

    public function date_summary() {
        $start_date = new DateTime($this->start_date);

        if($this->is_multiday()) {
            $end_date = new DateTime($this->end_date);

            return '<time datetime="'.$start_date->format('Y-m-d').'" class="dt-start">'
                    . $start_date->format('M j')
                    . '</time> - '
                    . '<time datetime="'.$end_date->format('Y-m-d').'" class="dt-end">'
                    . ($end_date->format('m') == $start_date->format('m') ? $end_date->format('j, Y') : $end_date->format('M j, Y'))
                    . '</time>';

        } else {
            if($this->start_time) {
                $start = new DateTime($this->start_date.' '.$this->start_time);
                return '<time datetime="'.$start_date->format('Y-m-d H:i').'" class="dt-start">'
                        . $start->format('M j, Y g:ia')
                        . '</time>';
            } else {
                return '<time datetime="'.$start_date->format('Y-m-d').'" class="dt-start">'
                        . $start_date->format('M j, Y')
                        . '</time>';
            }
        }
    }

    public function display_date() {
        $start_date = new DateTime($this->start_date);

        if($this->is_multiday()) {
            $end_date = new DateTime($this->end_date);

            return $start_date->format('F j') . ' - ' . $end_date->format('F j, Y') . '</time>';

        } else {
            return $start_date->format('F j, Y');
        }
    }

    public function display_time() {
        if(!$this->start_time)
            return '';

        $start_time = new DateTime($this->start_time);

        if($this->end_time) {
            $end_time = new DateTime($this->end_time);
            return $start_time->format('g:ia') . ' - ' . $end_time->format('g:ia');
        } else {
            return $start_time->format('g:ia');
        }
    }

    public function location_summary() {
        $str = [];
        if($this->location_address) $str[] = $this->location_address;
        if($this->location_locality) $str[] = $this->location_locality;
        if($this->location_region) $str[] = $this->location_region;
        if($this->location_country) $str[] = $this->location_country;
        return implode(', ', $str);
    }

    public function location_summary_with_name() {
        $str = [];
        if($this->location_name) $str[] = $this->location_name;
        if($this->location_address) $str[] = $this->location_address;
        if($this->location_locality) $str[] = $this->location_locality;
        if($this->location_region) $str[] = $this->location_region;
        if($this->location_country) $str[] = $this->location_country;
        return implode(', ', $str);
    }

    public function location_city() {
        $str = [];
        if($this->location_locality) $str[] = $this->location_locality;
        if(in_array($this->location_country, ['US', 'USA', 'United States'])) {
            if($this->location_region) $str[] = $this->location_region;
        } else {
            if($this->location_country) $str[] = $this->location_country;
            elseif($this->location_region) $str[] = $this->location_region;
        }
        return implode(', ', $str);
    }

    public function html() {
        if(!$this->description)
            return '';

        $markdown = $this->description;

        $html = \Michelf\MarkdownExtra::defaultTransform($markdown);

        $html = \p3k\HTML::sanitize($html);

        return $html;
    }

}
