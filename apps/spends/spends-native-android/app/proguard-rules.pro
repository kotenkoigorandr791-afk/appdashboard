-keepattributes *Annotation*,Signature,InnerClasses,EnclosingMethod
-keep class kotlinx.serialization.** { *; }
-keepclassmembers class **$$serializer { *; }
-keepclassmembers class * {
    @kotlinx.serialization.Serializable <fields>;
}
-dontwarn kotlinx.serialization.**
-keep class okhttp3.** { *; }
-dontwarn okhttp3.**
-keep class okio.** { *; }
-dontwarn okio.**
-keep class orbit.nex.finance.data.** { *; }
