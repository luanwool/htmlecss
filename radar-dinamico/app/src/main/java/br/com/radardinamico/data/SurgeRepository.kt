package br.com.radardinamico.data

import android.content.ContentValues
import android.content.Context
import android.database.sqlite.SQLiteDatabase
import android.database.sqlite.SQLiteOpenHelper
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow

/** Histórico local de dinâmicos (SQLite). Alimenta alertas, mapa e ranking de regiões. */
class SurgeRepository private constructor(ctx: Context) :
    SQLiteOpenHelper(ctx, "surges.db", null, 1) {

    private val _recent = MutableStateFlow<List<SurgeEvent>>(emptyList())
    /** Leituras das últimas 3 horas, atualizadas a cada inserção. */
    val recent: StateFlow<List<SurgeEvent>> = _recent

    override fun onCreate(db: SQLiteDatabase) {
        db.execSQL(
            """CREATE TABLE surge (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ts INTEGER NOT NULL, lat REAL NOT NULL, lng REAL NOT NULL,
                kind TEXT NOT NULL, value REAL NOT NULL, source TEXT NOT NULL, note TEXT)"""
        )
        db.execSQL("CREATE INDEX idx_surge_ts ON surge(ts)")
    }

    override fun onUpgrade(db: SQLiteDatabase, oldVersion: Int, newVersion: Int) = Unit

    @Synchronized
    fun insert(e: SurgeEvent): SurgeEvent {
        val id = writableDatabase.insert("surge", null, ContentValues().apply {
            put("ts", e.timestamp); put("lat", e.lat); put("lng", e.lng)
            put("kind", e.kind.name); put("value", e.value)
            put("source", e.source.name); put("note", e.note)
        })
        refreshRecent()
        return e.copy(id = id)
    }

    /** Evita gravar a mesma leitura repetidas vezes enquanto o motorista olha o mapa. */
    @Synchronized
    fun isDuplicate(e: SurgeEvent, windowMs: Long = 90_000, maxKm: Double = 0.5): Boolean =
        _recent.value.any {
            it.kind == e.kind && it.value == e.value && it.source == e.source &&
                e.timestamp - it.timestamp in 0..windowMs &&
                Geo.distanceKm(GeoPoint(it.lat, it.lng), GeoPoint(e.lat, e.lng)) <= maxKm
        }

    fun since(fromMs: Long): List<SurgeEvent> = query("ts >= ?", arrayOf(fromMs.toString()))

    fun all(limit: Int = 20_000): List<SurgeEvent> = query(null, null, limit)

    fun count(): Long = readableDatabase.compileStatement("SELECT COUNT(*) FROM surge").simpleQueryForLong()

    fun clear() { writableDatabase.delete("surge", null, null); refreshRecent() }

    fun refreshRecent() { _recent.value = since(System.currentTimeMillis() - 3 * 3600_000L) }

    private fun query(where: String?, args: Array<String>?, limit: Int? = null): List<SurgeEvent> {
        val out = mutableListOf<SurgeEvent>()
        readableDatabase.query("surge", null, where, args, null, null, "ts DESC", limit?.toString()).use { c ->
            while (c.moveToNext()) {
                out += SurgeEvent(
                    id = c.getLong(0), timestamp = c.getLong(1), lat = c.getDouble(2), lng = c.getDouble(3),
                    kind = SurgeKind.valueOf(c.getString(4)), value = c.getDouble(5),
                    source = SurgeSource.valueOf(c.getString(6)), note = c.getString(7),
                )
            }
        }
        return out
    }

    companion object {
        @Volatile private var instance: SurgeRepository? = null
        fun get(ctx: Context): SurgeRepository =
            instance ?: synchronized(this) {
                instance ?: SurgeRepository(ctx.applicationContext).also { it.refreshRecent(); instance = it }
            }
    }
}
