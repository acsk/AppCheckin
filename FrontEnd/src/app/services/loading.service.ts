import { Injectable } from '@angular/core';
import { BehaviorSubject, Observable } from 'rxjs';
import { map } from 'rxjs/operators';

@Injectable({
  providedIn: 'root'
})
export class LoadingService {
  private pendingRequests$ = new BehaviorSubject<number>(0);

  get isLoading$(): Observable<boolean> {
    return this.pendingRequests$.pipe(map((count) => count > 0));
  }

  show(): void {
    this.pendingRequests$.next(this.pendingRequests$.value + 1);
  }

  hide(): void {
    const nextValue = Math.max(0, this.pendingRequests$.value - 1);
    this.pendingRequests$.next(nextValue);
  }
}
