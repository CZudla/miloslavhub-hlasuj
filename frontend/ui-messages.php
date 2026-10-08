<?php
/** Pure UI-literal translator shared by both deployable components. */
if (!class_exists('MHL_UI_Messages',false)) {
    final class MHL_UI_Messages {
        public static function replace(string $source,array $messages,bool $html=false): string {
            $encode=static fn($text)=>$html?htmlspecialchars($text,ENT_QUOTES,'UTF-8',false):$text;
            if (isset($messages[$source])) { return $encode($messages[$source]); }
            static $previous=null,$patterns=array();
            if ($previous!==$messages) {
                $previous=$messages;$patterns=array();$keys=array_keys($messages);
                usort($keys,static fn($a,$b)=>strlen($b)<=>strlen($a));
                // Bounded alternatives avoid PCRE's compiled-pattern size limit.
                foreach (array_chunk($keys,40) as $chunk) {
                    $patterns[]='~(?<![\p{L}\p{N}_])(?:'.implode('|',array_map(static fn($key)=>preg_quote($key,'~'),$chunk)).')(?![\p{L}\p{N}_])~u';
                }
            }
            $matches=array();
            foreach ($patterns as $pattern) {
                $result=preg_match_all($pattern,$source,$found,PREG_OFFSET_CAPTURE);
                if ($result===false) { throw new RuntimeException('Invalid UI message catalog.'); }
                foreach ($found[0] as $match) { $matches[]=$match; }
            }
            usort($matches,static fn($a,$b)=>$a[1]<=>$b[1]?:strlen($b[0])<=>strlen($a[0]));
            $output='';$offset=0;
            foreach ($matches as [$key,$start]) {
                if ($start<$offset) { continue; }
                $output.=substr($source,$offset,$start-$offset).$encode($messages[$key]);$offset=$start+strlen($key);
            }
            return $output.substr($source,$offset);
        }
    }
}
